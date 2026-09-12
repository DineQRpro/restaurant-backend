<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\LookupCustomerRequest;
use App\Http\Requests\Api\V1\Customers\StoreCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    public function lookup(LookupCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->findByPhone($request->validated('phone'));

        return response()->json([
            'success' => true,
            'message' => $customer
                ? 'Customer found.'
                : 'Customer not found.',
            'data' => [
                'found' => $customer !== null,
                'customer' => $customer ? new CustomerResource($customer) : null,
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer saved successfully.',
            'data' => new CustomerResource($customer),
        ], 201);
    }
}
