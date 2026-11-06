<?php

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class InventoryIndexRequest extends FormRequest
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
        ];
    }

    /**
     * @return array{search?: string, is_active?: bool}
     */
    public function filters(): array
    {
        $filters = [];

        if ($this->filled('search')) {
            $filters['search'] = trim((string) $this->input('search'));
        }

        if ($this->filled('is_active')) {
            $filters['is_active'] = $this->boolean('is_active');
        }

        return $filters;
    }
}
