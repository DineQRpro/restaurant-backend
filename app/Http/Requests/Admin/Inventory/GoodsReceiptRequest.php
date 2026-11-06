<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\GoodsReceiptPaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('is_active', true)],
            'vendor_bill_number' => ['required', 'string', 'max:50'],
            'vendor_bill_date' => ['required', 'date'],
            'receipt_date' => ['required', 'date'],
            'payment_status' => ['required', Rule::enum(GoodsReceiptPaymentStatus::class)],
            'paid_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string'],
            'action' => ['nullable', Rule::in(['save', 'post'])],
            'confirm_zero_prices' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:50'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptData(): array
    {
        $data = $this->validated();
        unset($data['action'], $data['confirm_zero_prices']);

        foreach ($data['lines'] as &$line) {
            $line['gst_rate'] = $line['gst_rate'] ?? '0';
        }

        return $data;
    }
}
