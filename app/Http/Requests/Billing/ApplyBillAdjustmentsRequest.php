<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class ApplyBillAdjustmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('discount_id') === '' || $this->input('discount_id') === '0') {
            $this->merge(['discount_id' => null]);
        }

        if ($this->input('custom_percentage') === '') {
            $this->merge(['custom_percentage' => null]);
        }

        if ($this->input('comment') === '') {
            $this->merge(['comment' => null]);
        }

        if ($this->input('additional_charges') === '' || $this->input('additional_charges') === null) {
            $this->merge(['additional_charges' => '0']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'discount_id' => ['nullable', 'integer', 'exists:discounts,id'],
            'custom_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string', 'max:255'],
            'additional_charges' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'additional_charges.regex' => 'Additional charges must have at most 2 decimal places.',
        ];
    }
}
