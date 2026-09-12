<?php

namespace App\Http\Requests\Admin\Tables;

use Illuminate\Foundation\Http\FormRequest;

class IndexTableRequest extends FormRequest
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
     * @return array{
     *     search?: string,
     *     is_active?: bool
     * }
     */
    public function filters(): array
    {
        $filters = $this->validated();

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
