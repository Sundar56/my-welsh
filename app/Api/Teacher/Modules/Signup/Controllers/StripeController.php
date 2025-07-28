<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Signup\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\StripeService;
use App\Services\StripeWebhookService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeController extends BaseController
{
    use ApiResponse;

    /**
     * @var StripeService
     */
    protected $stripeService;
    /**
     * @var StripeWebhookService
     */
    protected $stripeWebhookService;

    public function __construct(StripeService $stripeService, StripeWebhookService $stripeWebhookService)
    {
        $this->stripeService = $stripeService;
        $this->stripeWebhookService = $stripeWebhookService;
    }
    /**
     * Handle create Payment Intent in stripe.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePaymentIntent(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->stripeService->createPaymentIntent($request)
        );
    }
    /**
     * Handles incoming Stripe webhook requests.
     *
     * This method validates the webhook signature, logs details if enabled,
     * processes the event data, and returns appropriate HTTP responses.
     *
     * @param \Illuminate\Http\Request $request  The incoming HTTP request from Stripe.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function stripeWebhook(Request $request)
    {
        $context = $this->stripeWebhookService->initializeStripeWebhookContext($request);
        extract($context);

        if ($stripelogEnabled === 1) {
            $this->stripeWebhookService->logStripeWebhook($currentMethod, $endpoint_secret, $sig_header, $payload);
        }
        $response = $this->stripeWebhookService->handleStripeEvent($payload, $sig_header, $endpoint_secret, $stripelogEnabled);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }
    }
}
