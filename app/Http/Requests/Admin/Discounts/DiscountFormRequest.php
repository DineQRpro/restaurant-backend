<?php

namespace App\Http\Requests\Admin\Discounts;

use App\Enums\DiscountAppliesTo;
use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class DiscountFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }

        foreach (['start_date', 'end_date'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        foreach (['min_level', 'max_level', 'value'] as $field) {
            if ($this->input($field) === '' || $this->input($field) === null) {
                $this->merge([$field => 0]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function discountRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('discounts', 'name')->ignore($ignoreId)],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_level' => ['required', 'integer', 'min:0', 'max:100'],
            'max_level' => ['required', 'integer', 'min:0', 'max:100', 'gte:min_level'],
            'applies_to' => ['required', Rule::enum(DiscountAppliesTo::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
