<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Api\Teacher\Modules\Signup\Models\StripeApiHistory;
use App\Models\UserPayments;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeService
{
    use ApiResponse, TransactionWrapper;

    /**
     * Creates a new customer in the payment gateway.
     *
     * @param Request $request  The HTTP request containing customer details.
     * @param int     $userId   The ID of the user to associate with the customer.
     *
     * @return array  The created customer details.
     */
    public function createCustomers(Request $request, int $userId)
    {
        return $this->runInTransaction(function () use ($request, $userId) {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $requestData = $this->requestData($request);
            $customer = $this->handleCreateCustomer($requestData);
            $intentId = $request->client_secret_id;
            Log::channel('webhooks')->info("Storing customer token", [
                'customer_id' => $customer->id,
                'user_id' => $userId,
                'intent_id' => $intentId,
            ]);
            $this->storeCustomerToken($customer->id, $userId, $intentId);
            $this->updateApiHistory($request, $customer, 'customer', $requestData, $userId);

            return $this->successResponse(null, 'Created Customer and Charge successfully');
        });
    }
    /**
     * Creates a new customer in the payment gateway.
     *
     * @param Request $request  The HTTP request containing customer details.
     *
     * @return array  The created customer details.
     */
    public function createPaymentIntent(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $resourceId = $this->decryptedValues($request->resource_id);
            $resource = Resources::select('annual_fee')->where('id', $resourceId)->first();
            $data = $this->storePaymentIntent($request, $resource->annual_fee);
            $redirectUrl = $this->getRedirectUrl('/signup');
            $data['redirectUrl'] = $redirectUrl;

            return $this->successResponse($data, 'Payment Intent created successfully');
        });
    }
    /**
     * Create and store a new payment intent.
     *
     * @param float|int $amount  The amount to be charged (in the smallest currency unit, e.g., cents).
     *
     * @return array  Returns the created PaymentIntent object.
     */
    public function storePaymentIntent(Request $request, $amount): array
    {
        $requestData = [
            'amount' => $amount,
            'currency' => env('CURRENCY'),
            'payment_method_types' => ['card'],
            'confirmation_method' => 'automatic',
        ];
        $intent = \Stripe\PaymentIntent::create($requestData);
        Log::channel('webhooks')->info("Stripe intent:: {$intent} \n");
        $this->updateApiHistory($request, $intent, 'payment intent', $requestData, null);

        return [
            'clientSecret' => $intent->client_secret,
        ];
    }
    /**
     * Creates a new customer in Stripe using the provided email and payment token.
     *
     * @param array $requestData
     *
     * @return \Stripe\Customer
     *
     * @throws \Stripe\Exception\ApiErrorException
     */
    private function handleCreateCustomer(array $requestData)
    {
        return \Stripe\Customer::create([
            'email' => $requestData['email'],
            'source' => $requestData['token'],
        ]);
    }
    /**
     * Stores the Stripe customer token associated with a user.
     *
     * @param string $customerId  The Stripe customer ID to store.
     * @param int $userId         The application's user ID the token is associated with.
     * @param string $intentId  The Stripe Payment intent ID to store.
     *
     * @return void
     */
    private function storeCustomerToken(string $customerId, int $userId, string $intentId): void
    {
        UserPayments::create([
            'user_id' => $userId,
            'customer_token' => $customerId,
            'intent_id' => $intentId,
            'status' => 0,
        ]);
    }
    /**
     * Extract token and email from the request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    private function requestData(Request $request): array
    {
        return [
            'token' => $request->token,
            'email' => $request->email,
        ];
    }
    /**
     * Stores API interaction history for auditing or debugging purposes.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     * @param mixed $data The data to be logged (e.g., PaymentIntent response).
     * @param string $method A label or tag for the type of operation (e.g., 'payment intent').
     * @param array $requestData
     * @param int|null $userId
     *
     * @return void
     */
    private function updateApiHistory(Request $request, $data, string $method, array $requestData, ?int $userId): void
    {
        StripeApiHistory::create([
            'request_id' => $data->id ?? null,
            'live_mode' => $data->livemode ? 1 : 0,
            'type' => $data->object ?? null,
            'method' => $method ?? null,
            'request_data' => json_encode($requestData),
            'response_data' => json_encode($data),
            'stripe_fee' => $data->application_fee_amount ?? null,
            'amount' => $data->amount ?? null,
            'currency' => $data->currency ?? null,
            'description' => $data->description ?? null,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'user_id' => $userId ?? null,
            'customer_token' => $data->id,
        ]);
    }
}
