<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKotRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.status' => ['sometimes', 'nullable', 'string', Rule::in(OrderItemStatus::values())],
            'items.*.special_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
