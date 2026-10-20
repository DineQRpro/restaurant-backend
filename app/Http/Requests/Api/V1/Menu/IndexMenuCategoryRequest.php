<?php

namespace App\Http\Requests\Api\V1\Menu;

use App\Models\MenuType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMenuCategoryRequest extends FormRequest
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
            'menu_type' => ['sometimes', 'nullable', 'string', Rule::in([MenuType::SLUG_FOOD, MenuType::SLUG_HOT_BEVERAGES])],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:name,sort_order,is_active,created_at'],
            'order' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
