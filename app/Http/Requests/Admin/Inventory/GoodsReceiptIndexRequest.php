<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\GoodsReceiptPaymentStatus;
use App\Enums\GoodsReceiptStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceiptIndexRequest extends FormRequest
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
            'receipt_number' => ['nullable', 'string', 'max:20'],
            'vendor_id' => ['nullable', 'integer'],
            'vendor_bill_number' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(GoodsReceiptStatus::class)],
            'payment_status' => ['nullable', Rule::enum(GoodsReceiptPaymentStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['receipt_number', 'vendor_id', 'vendor_bill_number', 'from', 'to', 'status', 'payment_status']),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }
}
