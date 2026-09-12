<?php

namespace App\Http\Requests\Api\V1\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^[6-9][0-9]{9}$/'],
            'name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
