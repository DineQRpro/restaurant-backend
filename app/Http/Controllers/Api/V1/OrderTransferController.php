<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\TransferOrderRequest;
use App\Models\Order;
use App\Services\Table\TableTransferService;
use Illuminate\Http\JsonResponse;

class OrderTransferController extends Controller
{
    public function __construct(
        private readonly TableTransferService $transferService,
    ) {}

    public function options(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Transfer table options retrieved successfully.',
            'data' => $this->transferService->options($order, request()->user()),
        ]);
    }

    public function store(TransferOrderRequest $request, Order $order): JsonResponse
    {
        $result = $this->transferService->transfer(
            $order,
            $request->user(),
            $request->transferType(),
            $request->destinationTableId(),
            $request->targetOrderId(),
        );

        $message = match ($result['type']) {
            'share' => 'Order shared to the selected table.',
            'join' => 'Party joined into the selected order.',
            default => 'Order moved to the selected table.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }
}
