<?php

namespace App\Http\Requests\Admin\Discounts;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscountStatusRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}
