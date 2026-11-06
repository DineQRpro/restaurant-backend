<?php

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer'],
            'item_id' => ['nullable', 'integer'],
            'receipt_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:150'],
            'scope' => ['nullable', 'in:low,all'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(['search', 'category_id', 'item_id', 'receipt_id', 'q', 'scope']);
    }
}
