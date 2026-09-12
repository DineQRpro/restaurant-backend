<?php

namespace App\Http\Requests\Admin\Menu;

use App\Enums\DietaryType;
use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('sort_order') === '') {
            $this->merge(['sort_order' => null]);
        }

        if ($this->input('spicy_level') === '') {
            $this->merge(['spicy_level' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var MenuItem $item */
        $item = $this->route('item');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('menu_categories', 'id')->where(function ($query) use ($item) {
                    $query->where('is_active', true)
                        ->orWhere('id', $item->category_id);
                }),
            ],
            'dietary_type' => ['required', Rule::enum(DietaryType::class)],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],
            'is_available' => ['sometimes', 'boolean'],
            'is_chef_special' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'spicy_level' => ['nullable', 'integer', 'min:0', 'max:2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category is invalid or inactive.',
        ];
    }
}
