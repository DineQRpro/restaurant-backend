<?php

namespace App\Http\Requests\Admin\Menu;

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
            'is_active' => ['sometimes', 'nullable', 'in:0,1'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:name,sort_order,is_active,created_at'],
            'order' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * @return array{
     *     search?: string,
     *     menu_type: string,
     *     is_active?: bool,
     *     sort?: string,
     *     order?: string
     * }
     */
    public function filters(): array
    {
        $filters = $this->validated();

        $filters['menu_type'] = $this->filled('menu_type')
            ? (string) $this->input('menu_type')
            : MenuType::SLUG_FOOD;

        if (! $this->filled('search')) {
            unset($filters['search']);
        }

        if (! $this->filled('is_active')) {
            unset($filters['is_active']);
        } else {
            $filters['is_active'] = $this->boolean('is_active');
        }

        return $filters;
    }
}
