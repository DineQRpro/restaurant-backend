<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BillResource;
use App\Models\Order;
use App\Models\Table;
use App\Services\Billing\BillCalculationService;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly BillCalculationService $calculator,
    ) {}

    public function preview(Table $table): JsonResponse
    {
        $preview = $this->billingService->previewForTable($table);
        $calculation = $preview['calculation'];
        $order = $preview['order'];
        $tableModel = $preview['table'];

        return response()->json([
            'success' => true,
            'message' => 'Checkout preview generated successfully.',
            'data' => $this->previewPayload($order, $tableModel, $calculation),
        ]);
    }

    public function previewOrder(Order $order): JsonResponse
    {
        $preview = $this->billingService->previewForOrder($order);
        $calculation = $preview['calculation'];

        return response()->json([
            'success' => true,
            'message' => 'Checkout preview generated successfully.',
            'data' => $this->previewPayload($preview['order'], $preview['table'], $calculation),
        ]);
    }

    public function store(Order $order): JsonResponse
    {
        $bill = $this->billingService->checkout($order);

        return response()->json([
            'success' => true,
            'message' => 'Bill generated successfully.',
            'data' => (new BillResource(
                $bill,
                $this->calculator->taxBreakdown((float) $bill->taxable_amount),
            ))->resolve(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, mixed>
     */
    private function previewPayload(Order $order, ?Table $table, array $calculation): array
    {
        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
            ],
            'table' => $table ? [
                'id' => $table->id,
                'number' => $order->tableDisplayName(),
                'status' => $table->status instanceof \BackedEnum ? $table->status->value : $table->status,
            ] : null,
            'subtotal' => number_format($calculation['subtotal'], 2, '.', ''),
            'discount_amount' => number_format($calculation['discount_amount'], 2, '.', ''),
            'taxable_amount' => number_format($calculation['taxable_amount'], 2, '.', ''),
            'tax_amount' => number_format($calculation['tax_amount'], 2, '.', ''),
            'round_off' => number_format($calculation['round_off'], 2, '.', ''),
            'grand_total' => number_format($calculation['grand_total'], 2, '.', ''),
            'gst' => [
                'cgst_percent' => number_format($calculation['cgst_percent'], 2, '.', ''),
                'sgst_percent' => number_format($calculation['sgst_percent'], 2, '.', ''),
                'cgst_amount' => number_format($calculation['cgst_amount'], 2, '.', ''),
                'sgst_amount' => number_format($calculation['sgst_amount'], 2, '.', ''),
            ],
        ];
    }
}
