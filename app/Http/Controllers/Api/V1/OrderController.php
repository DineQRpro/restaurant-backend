<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->place(
            $request->validated(),
            $request->user(),
        );

        $created = $order->wasRecentlyCreated;

        return response()->json([
            'success' => true,
            'message' => $created
                ? 'Order placed successfully.'
                : 'Items added to the order.',
            'data' => new OrderResource($order),
        ], $created ? 201 : 200);
    }
}
