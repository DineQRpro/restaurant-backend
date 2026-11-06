<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }

        $description = $this->input('description');
        $this->merge([
            'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
            'is_active' => $this->boolean('is_active'),
        ]);

        $this->offsetUnset('code');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $categoryId = $item instanceof InventoryItem ? $item->inventory_category_id : null;
        $unitId = $item instanceof InventoryItem ? $item->unit_of_measure_id : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'inventory_category_id' => [
                'required',
                'integer',
                Rule::exists('inventory_categories', 'id')->where(function ($query) use ($categoryId): void {
                    $query->where(function ($query) use ($categoryId): void {
                        $query->where('is_active', true);
                        if ($categoryId !== null) {
                            $query->orWhere('id', $categoryId);
                        }
                    });
                }),
            ],
            'unit_of_measure_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(function ($query) use ($unitId): void {
                    $query->where(function ($query) use ($unitId): void {
                        $query->where('is_active', true);
                        if ($unitId !== null) {
                            $query->orWhere('id', $unitId);
                        }
                    });
                }),
            ],
            'reorder_level' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'maximum_level' => ['required', 'numeric', 'min:0', 'gte:reorder_level', 'decimal:0,3'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maximum_level.gte' => 'Maximum stock level must be greater than or equal to the reorder level.',
            'inventory_category_id.exists' => 'Select an active item category.',
            'unit_of_measure_id.exists' => 'Select an active unit of measure.',
        ];
    }
}
