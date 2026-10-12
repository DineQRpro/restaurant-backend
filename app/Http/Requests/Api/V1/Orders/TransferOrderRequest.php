<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Enums\TransferType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferOrderRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in([
                TransferType::Move->value,
                TransferType::Share->value,
                TransferType::Join->value,
            ])],
            'destination_table_id' => ['required', 'integer', 'exists:tables,id'],
            'target_order_id' => ['nullable', 'integer', 'exists:orders,id', 'required_if:type,'.TransferType::Join->value],
        ];
    }

    public function transferType(): TransferType
    {
        return TransferType::from($this->validated('type'));
    }

    public function destinationTableId(): int
    {
        return (int) $this->validated('destination_table_id');
    }

    public function targetOrderId(): ?int
    {
        $value = $this->validated('target_order_id') ?? null;

        return $value === null ? null : (int) $value;
    }
}
