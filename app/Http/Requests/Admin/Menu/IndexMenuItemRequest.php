<?php

namespace App\Http\Requests\Admin\Menu;

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
            'is_available' => ['sometimes', 'nullable', 'in:0,1'],
            'is_chef_special' => ['sometimes', 'nullable', 'in:0,1'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:name,price,sort_order,created_at,is_available'],
            'order' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * @return array{
     *     search?: string,
     *     category_id?: int,
     *     dietary_type?: string,
     *     is_available?: bool,
     *     is_chef_special?: bool,
     *     sort?: string,
     *     order?: string
     * }
     */
    public function filters(): array
    {
        $filters = $this->validated();

        if (! $this->filled('search')) {
            unset($filters['search']);
        }

        if (! $this->filled('category_id')) {
            unset($filters['category_id']);
        } else {
            $filters['category_id'] = (int) $this->input('category_id');
        }

        if (! $this->filled('dietary_type')) {
            unset($filters['dietary_type']);
        }

        if (! $this->filled('is_available')) {
            unset($filters['is_available']);
        } else {
            $filters['is_available'] = $this->boolean('is_available');
        }

        if (! $this->filled('is_chef_special')) {
            unset($filters['is_chef_special']);
        } else {
            $filters['is_chef_special'] = $this->boolean('is_chef_special');
        }

        return $filters;
    }
}
