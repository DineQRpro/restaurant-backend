<?php

namespace App\Http\Requests\Admin\Tables;

use App\Models\Table;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $number = $this->input('number');

        if (is_string($number)) {
            $this->merge(['number' => trim($number)]);
        }

        foreach (['description', 'sort_order'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Table $table */
        $table = $this->route('table');

        return [
            'number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tables', 'number')->ignore($table),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:99'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
