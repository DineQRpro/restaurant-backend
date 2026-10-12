<?php

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderMergeTablesRequest extends FormRequest
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
            'table_ids' => ['present', 'array'],
            'table_ids.*' => ['integer', 'distinct', 'exists:tables,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function tableIds(): array
    {
        return array_values(array_map('intval', $this->validated('table_ids') ?? []));
    }
}
