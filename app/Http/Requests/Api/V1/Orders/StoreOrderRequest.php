<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Models\DineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'dine_type_id' => [
                'required',
                'integer',
                Rule::exists('dine_types', 'id')->where('is_active', true),
            ],
            'table_id' => ['nullable', 'integer', 'exists:tables,id'],
            'pax' => ['sometimes', 'integer', 'min:1', 'max:40'],
            'allocated_seats' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'order_id' => ['sometimes', 'integer', 'exists:orders,id'],
            'merged_table_ids' => ['sometimes', 'array'],
            'merged_table_ids.*' => ['integer', 'distinct', 'exists:tables,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.packing_fee' => ['sometimes', 'numeric', 'min:0'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $dineType = DineType::query()->find($this->integer('dine_type_id'));

            if ($dineType && ! $dineType->is_active) {
                $validator->errors()->add('dine_type_id', 'The selected dine type is inactive.');
            }

            if ($dineType?->requiresTable() && ! $this->filled('table_id')) {
                $validator->errors()->add('table_id', 'A table is required for this dine type.');
            }
        });
    }
}
