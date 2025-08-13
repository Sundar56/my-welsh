<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Teacher\Modules\Signup\Models\StripeWebhooks;
use App\Models\User;
use App\Models\UserPayments;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookService
{
    use ApiResponse, TransactionWrapper;

    /**
     * Initializes and extracts context variables needed for processing a Stripe webhook.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>
     */
    public function initializeStripeWebhookContext(Request $request): array
    {
        $settings = $this->getSuperadminSettings();
        $webhookKey = $settings['webhookkey'] ?? config('services.stripe.webhook_secret');

        return [
            'statusCode' => 400,
            'responsecode' => 0,
            'stripelogEnabled' => (int) env('STRIPE_LOG', 0),
            'currentMethod' => strtolower($request->method()),
            'payload' => $request->getContent(),
            'payloadresponse' => json_decode($request->getContent(), true),
            'endpoint_secret' => $webhookKey,
            'sig_header' => $request->header('Stripe-Signature', ''),
            'event' => null,
        ];
    }
    /**
     * Logs Stripe webhook details to the 'webhooks' log channel.
     *
     * @param string $method           HTTP request method (e.g., "post")
     * @param string $endpointSecret  Stripe webhook secret from config
     * @param string $signature        Stripe signature from the request header
     * @param string $payload          Raw JSON payload from the webhook
     *
     * @return void
     */
    public function logStripeWebhook($method, $endpointSecret, $signature, $payload)
    {
        Log::channel('webhooks')->info("Enter stripe webhooks \n");
        Log::channel('webhooks')->info("Stripe method:: {$method} \n");
        Log::channel('webhooks')->info("Stripe endpoint_secret:: {$endpointSecret} \n");
        Log::channel('webhooks')->info("Stripe signature:: {$signature} \n");
        Log::channel('webhooks')->info("Stripe payload:: {$payload} \n");
    }
    /**
     * Handles the construction and validation of the Stripe event, including logging and error handling.
     *
     * @param string $payload            Raw JSON payload from Stripe webhook.
     * @param string $sigHeader          Stripe signature header.
     * @param string $endpointSecret     Stripe endpoint secret.
     * @param int    $logEnabled         Whether to enable logging (1 = enabled).
     *
     * @return \Stripe\Event|JsonResponse|null
     */
    public function handleStripeEvent(string $payload, string $sigHeader, string $endpointSecret, int $logEnabled)
    {
        try {
            $event = $this->constructStripeEvent($payload, $sigHeader, $endpointSecret);

            if ($this->isEmptyEvent($event)) {
                return $this->handleEmptyEvent($logEnabled);
            }

            return $this->processStripeEvent($event, $logEnabled);
        } catch (\Throwable $e) {
            return $this->resolveStripeExceptionResponse($e, $logEnabled);
        }
    }
    /**
     * Handles Stripe-related exceptions with logging and structured error response.
     *
     * @param \Exception $e             The caught exception.
     * @param string     $logMessage    Message to log before the exception.
     * @param string     $responseKey   Key to use in the JSON error response.
     * @param int        $statusCode    HTTP response status code.
     * @param int        $logEnabled    Whether logging is enabled.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleStripeException(\Exception $e, string $logMessage, string $responseKey, int $statusCode, int $logEnabled)
    {
        if ($logEnabled) {
            Log::channel('webhooks')->error("{$logMessage}\n");
            Log::channel('webhooks')->error(json_encode($e->getMessage()));
        }

        return response([$responseKey => $e->getMessage()], $statusCode);
    }
    /**
     * Logs Stripe webhook details to the 'webhooks' log channel.
     *
     * @param string $eventType           HTTP request method (e.g., "post")
     * @param string $event  Stripe webhook secret from config
     *
     * @return void
     */
    public function logEventData(string $eventType, $event): void
    {
        $this->logToWebhookChannel("Stripe event: {$eventType}", $event);
    }
    /**
     * Logs Stripe webhook details to the 'webhooks' log channel.
     *
     * @param array $webhookinputdata      HTTP request method (e.g., "post")
     *
     * @return void
     */
    public function logWebhookinputdata(?array $webhookinputdata): void
    {
        $this->logToWebhookChannel('Webhook input data', $webhookinputdata);
    }
    /**
     * Builds the array of data for Stripe webhook processing.
     *
     * @param \Stripe\Event     $event        The Stripe event object.
     * @param \stdClass        $eventRequest The Stripe event request data.
     * @param \stdClass        $eventObject  The Stripe event object data.
     * @param Carbon|null $created_at  Timestamp as Carbon object or null
     *
     * @return array
     */
    public function buildWebhookInputData($event, $eventRequest, $eventObject, ?Carbon $created_at): array
    {
        return [
            'stripe_event_id' => $event->id,
            'stripe_event_type' => $event->type,
            'stripe_request_id' => $eventRequest->id ?? null,
            'stripe_request_idempotency_key' => $eventRequest->idempotency_key ?? null,
            'stripe_api_version' => $event->api_version,
            'stripe_mode' => $event->livemode,
            'stripe_object_id' => $eventObject->id,
            'stripe_customer_name' => $eventObject->name ?? null,
            'stripe_customer_email' => $eventObject->email ?? null,
            'stripe_amount' => isset($eventObject->amount) ? $eventObject->amount / 100 : 0,
            'stripe_currency' => $eventObject->currency ?? null,
            'stripe_capture_method' => $eventObject->capture_method ?? null,
            'stripe_status' => $eventObject->status ?? null,
            'webhookstatus' => 1,
            'stripe_data' => $event->data,
            'created_at' => $created_at,
        ];
    }
    /**
     * Handle the Stripe event for successful payment intent.
     *
     * @param object $eventObject
     *
     * @return void
     */
    public function handlePaymentIntentSucceeded($eventObject): void
    {
        if ($eventObject->status === 'succeeded') {
            $userPayment = UserPayments::where('intent_id', $eventObject->id)->first();
            $userPayment->update(['status' => UserSubscription::BACS]);
            $userId = $userPayment->user_id;
            if ($userId) {
                UserSubscription::where('user_id', $userId)->update([
                    'status' => UserSubscription::BACS,
                ]);
                User::where('id', $userId)->update([
                    'is_activated' => UserSubscription::BACS,
                ]);

                $user = $this->getUserById($userId);
                $this->sendUserEmail($user->email, null, 'activate', 'en', null);
            }
        }
    }
    /**
     * Logs structured data to the 'webhooks' log channel with a title.
     *
     * @param string $title  A short descriptive title or label for the log entry.
     * @param mixed  $data   The data to log (can be an array, object, or string).
     *
     * @return void
     */
    protected function logToWebhookChannel(string $title, $data): void
    {
        Log::channel('webhooks')->info("::::::::::::::::::::::::::::::::::::::::::::::::::\n");
        Log::channel('webhooks')->info($title);
        Log::channel('webhooks')->info(print_r($data, true));
        Log::channel('webhooks')->info("::::::::::::::::::::::::::::::::::::::::::::::::::\n");
    }
    /**
     * Check if the Stripe event data is empty.
     *
     * @param \Stripe\Event $event
     *
     * @return bool
     */
    private function isEmptyEvent($event): bool
    {
        return ! isset($event->data) || $event->data === null || $event->data === [];
    }
    /**
     * Handle an empty Stripe event response.
     *
     * @param int $logEnabled
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleEmptyEvent(int $logEnabled)
    {
        if ($logEnabled) {
            Log::channel('webhooks')->info("Stripe empty data \n");
        }

        return response(['error' => 'Invalid webhook'], 400);
    }
    /**
     * Construct and validate the Stripe event.
     */
    private function constructStripeEvent(string $payload, string $sigHeader, string $endpointSecret): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
    }
    /**
     * Handle and process the event data.
     */
    private function processStripeEvent(\Stripe\Event $event, int $logEnabled)
    {
        $created_at = Carbon::now();
        $eventObject = $event->data->object;
        $eventRequest = $event->request;
        $webhookInputData = $this->buildWebhookInputData($event, $eventRequest, $eventObject, $created_at);
        if ($logEnabled) {
            Log::channel('webhooks')->info('Stripe data:: ' . json_encode($event->data) . " \n");
            $this->logEventData($event->type, $event);
            $this->logWebhookinputdata($webhookInputData);
        }
        StripeWebhooks::storeWebhookHistory($webhookInputData);
        if ($event->type === 'payment_intent.succeeded') {
            $this->handlePaymentIntentSucceeded($eventObject);
        }
        return response(['message' => 'Webhook processed for ' . $event->type], 200);
    }
    /**
     * Determine the appropriate response based on the Stripe exception type.
     *
     * @param \Throwable $e The exception thrown.
     * @param int $logEnabled Flag indicating if logging is enabled.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function resolveStripeExceptionResponse(\Throwable $e, int $logEnabled)
    {
        return match (true) {
            $e instanceof \UnexpectedValueException => $this->handleStripeException($e, 'Invalid payload', 'invalidPayloadErr', 400, $logEnabled),

            $e instanceof \Stripe\Exception\SignatureVerificationException => $this->handleStripeException($e, 'Invalid signature', 'invalidSigErr', 400, $logEnabled),

            default => $this->handleStripeException($e, 'Enter stripe general error', 'error', 500, $logEnabled),
        };
    }
}
