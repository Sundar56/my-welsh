<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Customers\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Services\CustomerService;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomersController extends BaseController
{
    use ApiResponse;

    /**
     * @var CustomerService
     */
    protected $customerService;
    /**
     * @var SubscriptionService
     */
    protected $subscriptionService;

    public function __construct(CustomerService $customerService, SubscriptionService $subscriptionService)
    {
        $this->customerService = $customerService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle add customer.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addCustomers(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->addCustomers($request)
        );
    }
    /**
     * Handle customers listing.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function customersList(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->customersList($request)
        );
    }
    /**
     * Handle add customer.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewCustomer(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->viewCustomer($request)
        );
    }
    /**
     * Handle add customer.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editCustomer(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->editCustomer($request)
        );
    }
    /**
     * Handle get resources list for subscription.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscriptionTypeList(): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->subscriptionService->trailResourceList()
        );
    }
    /**
     * Handle user activation by Admin.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateCustomer(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->activateCustomer($request)
        );
    }
    /**
     * Handle get Billing emails.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBillingEmails(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->getBillingEmails($request)
        );
    }
    /**
     * Handle get Billing invoice users list.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bacsCustomerList(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->bacsCustomerList($request)
        );
    }
    /**
     * Handle activate customers by admin.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateCustomers(Request $request): JsonResponse
    {
        return $this->handleServiceResponse(
            $this->customerService->activateCustomers($request)
        );
    }
}
