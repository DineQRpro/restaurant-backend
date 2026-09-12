<?php

namespace App\Http\Requests\Api\V1\Menu;

use App\Enums\DietaryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMenuItemRequest extends FormRequest
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
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:menu_categories,id'],
            'dietary_type' => ['sometimes', 'nullable', Rule::enum(DietaryType::class)],
            'is_available' => ['sometimes', 'boolean'],
            'is_chef_special' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:name,price,sort_order,created_at,is_available'],
            'order' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
