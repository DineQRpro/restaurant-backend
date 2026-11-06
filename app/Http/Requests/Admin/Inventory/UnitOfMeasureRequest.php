<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\UnitDimension;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'symbol', 'description'] as $field) {
            $value = $this->input($field);
            $this->merge([
                $field => is_string($value) ? trim($value) : $value,
            ]);
        }

        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }

        $this->merge(['is_active' => $this->boolean('is_active')]);
        $this->offsetUnset('code');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');
        $unitId = $unit instanceof UnitOfMeasure ? $unit->id : null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('units_of_measure', 'name')->ignore($unitId)],
            'symbol' => ['required', 'string', 'max:20', Rule::unique('units_of_measure', 'symbol')->ignore($unitId)],
            'dimension' => ['required', Rule::enum(UnitDimension::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
