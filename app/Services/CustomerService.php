<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Api\Teacher\Modules\Signup\Models\BillingEmail;
use App\Api\Teacher\Modules\Signup\Models\BillingInvoiceUsers;
use App\Models\User;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use App\Traits\TransactionWrapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerService
{
    use ApiResponse, TransactionWrapper;

    protected DataSecurityService $dataSecurityService;
    protected LoginService $loginService;
    protected SubscriptionService $subscriptionService;

    public function __construct(DataSecurityService $dataSecurityService, LoginService $loginService, SubscriptionService $subscriptionService)
    {
        $this->dataSecurityService = $dataSecurityService;
        $this->loginService = $loginService;
        $this->subscriptionService = $subscriptionService;
    }
    /**
     * Handle add customer and assign a role.
     *
     * @param Request $request
     *
     * @return array
     */
    public function addCustomers(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $validationErrors = $this->validateCustomers($request);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $this->createCustomers($request);
            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.add_customer', [], $lang));
        });
    }
    /**
     * Handle the create customer
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCustomers($request)
    {
        $isSubscribed = (bool) $request->is_subscribed;
        $isTrail = (bool) $request->is_trail;
        $password = Str::random(10);
        $roleId = $request->user_type;
        $lang = $request->language ?? 'en';
        $data = $this->prepareUserData($request, $password);
        $user = $this->loginService->createUserFromRequest(
            $data,
            $isSubscribed,
            $isTrail
        );
        $this->assignRoleToUser($user, (int) $roleId);
        $this->handleUserSubscriptionAndTrial($request, $user->id, $isTrail);
        $this->sendUserEmail($request->email, $password, 'newuser', $lang, null);

        return $user;
    }
    /**
     * Handle customer listing and assign a role.
     *
     * @param Request $request
     *
     * @return array
     */
    public function customersList(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $validation = $this->validateCustomersList($request);
            if ($validation) {
                return $this->validationErrorResponse($validation);
            }
            $query = $request->type === UserSubscription::BACS ? $this->getBacsUsersQuery() : $this->getCustomersQuery();

            if ($request->filled('search')) {
                $this->applySearchFilters($query, $request->input('search'));
            }
            $lang = $request->query('language_code', 'en');
            $data = $this->paginateAndCustomersList($query, $request, $lang);

            return $this->successResponse($data, trans('message.success.customer_list', [], $lang));
        });
    }
    /**
     * Handle view customer details.
     *
     * @param Request $request
     *
     * @return array
     */
    public function viewCustomer(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $customerId = $this->decryptedValues($request->customer_id);
            $customer = $this->customerInfo($customerId);

            $lang = $request->language ?? 'en';

            return $this->successResponse($customer, trans('message.success.view_customer', [], $lang));
        });
    }
    /**
     * Handle edit customer details.
     *
     * @param Request $request
     *
     * @return array
     */
    public function editCustomer(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $customerId = $this->decryptedValues($request->customer_id);
            $validationErrors = $this->validateCustomers($request, $customerId);
            if ($validationErrors) {
                return $this->validationErrorResponse($validationErrors);
            }
            $this->updateCustomers($request, $customerId);
            $lang = $request->language ?? 'en';

            return $this->successResponse(null, trans('message.success.edit_customer', [], $lang));
        });
    }
    /**
     * Handle the update customer details
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCustomers(Request $request, int $customerId)
    {
        User::where('id', $customerId)->update([
            'email' => $request->email,
            'language_code' => $request->language_code,
            'is_subscribed' => $request->is_subscribed,
            'is_trail' => $request->is_trail,
            'name' => $request->name,
            'is_customer' => $request->is_customer ?? 0,
        ]);

        $this->handleUserSubscriptionAndTrial($request, $customerId, $request->is_trail);
    }
    /**
     * Get customer information by user ID.
     *
     * @param int $customerId The ID of the user.
     *
     * @return array
     */
    public function customerInfo(int $customerId): ?array
    {
        $customer = User::where('id', $customerId)->first();
        $subscripData = UserSubscription::where('user_id', $customerId)->first();
        $resource = Resources::where('id', $subscripData->resource_id)->first();
        return [
            'customerEmail' => $customer->email ?? null,
            'organisationName' => $customer->name ?? null,
            'resourceId' => $this->encryptedValues($subscripData->resource_id) ?? null,
            'resourceName' => $resource->resource_name ?? null,
        ];
    }
    /**
     * Handles activate user for BACS payment method users
     *
     * @param Request $request
     *
     * @return array
     */
    public function activateCustomer(Request $request): ?array
    {
        return $this->runInTransaction(function () use ($request) {
            $userId = $this->decryptedValues($request->customer_id);
            $lang = $request->language ?? 'en';
            $isActivated = $request->is_activated;
            $user = $this->getUserById($userId);
            if (! $user) {
                return $this->failedResponse(trans('message.login_val.username.exists', [], $lang));
            }
            $user->update(['is_activated' => $isActivated]);
            $this->sendUserEmail($user->email, null, 'activate', $lang, null);

            return $this->successResponse(null, trans('message.success.account_activate', [], $lang));
        });
    }
    /**
     * Retrieve all billing emails with encrypted IDs.
     *
     * @param Request $request
     *
     * @return array A success response containing the list of billing emails.
     */
    public function getBillingEmails($request): ?array
    {
        return $this->runInTransaction(function () use ($request) {
            [$page, $perPage, $orderColumn, $orderDirection] = $this->getPaginationParam($request);
            $query = BillingEmail::select('id', 'invoice_email');
            $paginated = $query->orderBy($orderColumn, $orderDirection)
                ->paginate($perPage, ['*'], 'page', $page);
            $lang = $request->language ?? 'en';
            $data = collect($paginated->items())->map(function ($billingEmail) {
                return $this->mapBillingEmailData($billingEmail);
            })->toArray();

            return $this->successResponse([
                'list' => $data,
                'currentPage' => $paginated->currentPage(),
                'totalPages' => $paginated->lastPage(),
                'recordsTotal' => $paginated->total(),
            ], trans('message.success.billing_list', [], $lang));
        });
    }
    /**
     * Handles activate user for BACS payment method users
     *
     * @param Request $request
     *
     * @return array
     */
    public function activateCustomers(Request $request): ?array
    {
        return $this->runInTransaction(function () use ($request) {
            $userIds = $request->customer_id;
            $isActivated = $request->is_activated;
            foreach ($userIds as $userId) {
                $decryptedId = $this->decryptedValues($userId);
                $user = $this->getUserById($decryptedId);

                if ($user) {
                    $user->update(['is_activated' => $isActivated]);

                    $this->sendUserEmail($user->email, null, 'activate', 'en', null);
                }
            }
            return $this->successResponse(null, 'User activated successflly');
        });
    }
    /**
     * Handle BACS customer listing based on billing invoice.
     *
     * @param Request $request
     *
     * @return array
     */
    public function bacsCustomerList(Request $request)
    {
        return $this->runInTransaction(function () use ($request) {
            $validation = $this->validateCustomersList($request);
            if ($validation) {
                return $this->validationErrorResponse($validation);
            }
            $invoiceId = $this->decryptedValues($request->invoice_id);
            $userIds = $this->getUserIdsByInvoice($invoiceId);
            $usersQuery = $this->getBacsCustomersQuery($userIds);

            if ($request->filled('search')) {
                $this->applySearchFilters($usersQuery, $request->input('search'));
            }
            $lang = $request->language ?? 'en';
            $data = $this->paginateAndCustomersList($usersQuery, $request, $lang);

            return $this->successResponse($data, trans('message.success.bacs_customer_list', [], $lang));
        });
    }
    /**
     * Handle user subscription and trial history setup.
     *
     * @param \Illuminate\Http\Request $request  The incoming request containing subscription and trial data.
     * @param int                      $userId   The ID of the user for whom the subscription and trial are handled.
     * @param bool                     $isTrail  Flag indicating whether the user is on a trial plan.
     *
     * @return void
     */
    protected function handleUserSubscriptionAndTrial($request, int $userId, bool $isTrail): void
    {
        $resourceId = $this->decryptedValues($request->resource_id);
        $type = $this->storeUserSubscription($resourceId, $userId);

        if ($isTrail) {
            $this->subscriptionService->buildTrailHistory($userId, $resourceId);
        } else {
            $this->buildSubscriptionHistory($type['id'], $resourceId);
        }
    }
    private function mapBillingEmailData($billingEmail): array
    {
        return [
            'invoiceId' => $this->encryptedValues($billingEmail->id),
            'invoiceEmail' => $billingEmail->invoice_email,
        ];
    }
    /**
     * Prepare user data from the request for user creation.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing user inputs.
     * @param string $password The password to assign to the user.
     *
     * @return array The prepared user data.
     */
    private function prepareUserData(Request $request, string $password): array
    {
        return [
            'email' => $request->email,
            'name' => $request->name,
            'password' => Hash::make($password),
            'payment_type' => UserSubscription::CARD,
            'language_code' => $this->getLanguageCodeValue($request->language_code),
            'isCustomer' => (int) $request->is_customer,
            'isActivated' => UserSubscription::STATUS_ONE,
        ];
    }
    /**
     * @param \Illuminate\Http\Request $request
     * @param int|null $userId
     *
     * @return array|null
     */
    private function validateCustomers(Request $request, ?int $customerId = null): ?array
    {
        $rules = [
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($customerId),
            ],
            'name' => 'required',
        ];
        $lang = $request->language ?? 'en';
        $messages = trans('message.customer_val', [], $lang);

        return $this->validateRequest($request->all(), $rules, $messages);
    }
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateCustomersList(Request $request): ?array
    {
        $rules = [
            'page' => 'sometimes|integer|min:1',
            'length' => 'sometimes|integer|min:1|max:500',
        ];

        return $this->validateRequest($request->all(), $rules);
    }
    /**
     * Builds and returns the base customer query with optional role or type filters.
     *
     * @param Request $request
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getCustomersQuery()
    {
        return User::leftJoin('user_subscription', function ($join) {
            $join->on('user_subscription.user_id', '=', 'users.id')
                ->where('user_subscription.latest_subscription', UserSubscription::STATUS_ONE);
        })
            ->leftJoin('learning_resources as resources', 'user_subscription.resource_id', '=', 'resources.id')
            ->where('users.payment_type', UserSubscription::CARD)
            ->select(
                'users.*',
                'resources.resource_name as resourceName',
                'user_subscription.status as paymentStatus'
            );
    }
    /**
     * Applies search filters to the customer query based on allowed columns.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     *
     * @return void
     */
    private function applySearchFilters($query, string $search): void
    {
        $columns = ['users.name', 'users.email', 'users.created_at'];

        $query->where(function ($q) use ($columns, $search) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $q->where($column, 'like', '%' . $search . '%');
                } else {
                    $q->orWhere($column, 'like', '%' . $search . '%');
                }
            }
        });
    }
    /**
     * Paginates and formats the customer query results into a structured response.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param string $lang
     *
     * @return array|null
     */
    private function paginateAndCustomersList($query, Request $request, string $lang): ?array
    {
        [$page, $perPage, $orderColumn, $orderDirection] = $this->getPaginationParams($request);

        $paginated = $query->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginated->items())->map(function ($item) use ($lang) {
            return $this->mapCustomerData($item, $lang);
        })->toArray();

        return [
            'list' => $data,
            'currentPage' => $paginated->currentPage(),
            'totalPages' => $paginated->lastPage(),
            'recordsTotal' => $paginated->total(),
        ];
    }
    /**
     * Maps a customer item to the desired data format.
     *
     * This function takes a customer object and transforms it into an array with
     * encrypted user ID, email, name, activation status, resource name, and payment status.
     *
     * @param  mixed  $item  The customer object (usually a User model).
     *
     * @return array         An array containing the customer's formatted data.
     */
    private function mapCustomerData($item, string $lang): array
    {
        return [
            'encryptedId' => Crypt::encrypt($item->id),
            'customerEmail' => $item->email,
            'customerName' => $item->name,
            'isActivated' => $item->is_activated,
            'resourceName' => $item->resourceName,
            'status' => $this->getTranslatedStatus($item->paymentStatus, $lang),
        ];
    }
    /**
     * Get the translated payment status based on the given language.
     *
     * @param int $status The numeric payment status (e.g., 1 for paid).
     * @param string $lang The language code (e.g., 'en' or 'cy').
     *
     * @return string Translated payment status.
     */
    private function getTranslatedStatus($status, string $lang): string
    {
        return $status === UserSubscription::STATUS_ONE
            ? trans('message.customers.paid', [], $lang)
            : trans('message.customers.awaiting_payment', [], $lang);
    }

    /**
     * Extracts and validates pagination and ordering parameters from the request.
     *
     * @param Request $request
     *
     * @return array [$page, $perPage, $orderColumn, $orderDirection]
     */
    private function getPaginationParams(Request $request): array
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('length', env('TABLE_LIST_LENGTH', 10));
        $orderColumn = $request->input('order_column', 'users.created_at');
        $orderDirection = $request->input('order_dir', 'desc');

        if (! in_array($orderDirection, ['asc', 'desc'])) {
            $orderDirection = 'desc';
        }

        $allowedOrderColumns = ['users.id', 'users.name', 'users.email', 'users.created_at'];
        if (! in_array($orderColumn, $allowedOrderColumns)) {
            $orderColumn = 'users.created_at';
        }

        return [$page, $perPage, $orderColumn, $orderDirection];
    }
    /**
     * Extracts and validates pagination and ordering parameters from the request.
     *
     * @param Request $request
     *
     * @return array [$page, $perPage, $orderColumn, $orderDirection]
     */
    private function getPaginationParam(Request $request): array
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 10);
        $orderColumn = $request->get('order_column', 'id');
        $orderDirection = $request->get('order_direction', 'desc');

        return [$page, $perPage, $orderColumn, $orderDirection];
    }
    /**
     * Builds and returns the base customer query with optional role or type filters.
     *
     * @param Request $request
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getBacsUsersQuery()
    {
        return User::leftJoin('user_subscription', function ($join) {
            $join->on('user_subscription.user_id', '=', 'users.id')
                ->where('user_subscription.latest_subscription', UserSubscription::STATUS_ONE);
        })
            ->leftJoin('learning_resources as resources', 'user_subscription.resource_id', '=', 'resources.id')
            ->where('users.payment_type', UserSubscription::BACS)
            ->select(
                'users.*',
                'resources.resource_name as resourceName',
                'user_subscription.status as paymentStatus'
            );
    }
    /**
     * Retrieve user IDs associated with a given billing invoice ID.
     *
     * @param int|string $invoiceId The decrypted billing invoice ID.
     *
     * @return array The array of user IDs linked to the invoice.
     */
    private function getUserIdsByInvoice($invoiceId): array
    {
        return BillingInvoiceUsers::where('billing_invoice_id', $invoiceId)
            ->pluck('user_id')
            ->toArray();
    }
    /**
     * Builds the query to retrieve BACS customers by an array of user IDs.
     *
     * @param array $userIds An array of user IDs to filter the BACS customers.
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder The query builder instance.
     */
    private function getBacsCustomersQuery(array $userIds)
    {
        return User::leftJoin('user_subscription', function ($join) {
            $join->on('user_subscription.user_id', '=', 'users.id')
                ->where('user_subscription.latest_subscription', UserSubscription::STATUS_ONE);
        })
            ->leftJoin('learning_resources as resources', 'user_subscription.resource_id', '=', 'resources.id')
            ->whereIn('users.id', $userIds)
            ->select(
                'users.*',
                'resources.resource_name as resourceName',
                'user_subscription.status as paymentStatus'
            );
    }
}
