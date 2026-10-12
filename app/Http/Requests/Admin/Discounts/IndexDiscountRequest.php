<?php

namespace App\Http\Requests\Admin\Discounts;

use App\Enums\DiscountAppliesTo;
use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDiscountRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'nullable', 'in:0,1'],
            'type' => ['sometimes', 'nullable', Rule::enum(DiscountType::class)],
            'applies_to' => ['sometimes', 'nullable', Rule::enum(DiscountAppliesTo::class)],
        ];
    }

    /**
     * @return array{
     *     search?: string,
     *     is_active?: bool,
     *     type?: string,
     *     applies_to?: string
     * }
     */
    public function filters(): array
    {
        $filters = $this->validated();

        if (! $this->filled('search')) {
            unset($filters['search']);
        }

        if (! $this->filled('is_active')) {
            unset($filters['is_active']);
        } else {
            $filters['is_active'] = $this->boolean('is_active');
        }

        if (! $this->filled('type')) {
            unset($filters['type']);
        }

        if (! $this->filled('applies_to')) {
            unset($filters['applies_to']);
        }

        return $filters;
    }
}
