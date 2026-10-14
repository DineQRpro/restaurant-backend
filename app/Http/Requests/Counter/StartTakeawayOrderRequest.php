<?php

namespace App\Http\Requests\Counter;

use Illuminate\Foundation\Http\FormRequest;

class StartTakeawayOrderRequest extends FormRequest
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
            'order_type' => ['required', 'in:take-away'],
            'customer_name' => ['required', 'string', 'max:150'],
            'mobile_number' => ['required', 'string', 'regex:/^[6-9][0-9]{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_type.required' => 'Select an order type.',
            'mobile_number.regex' => 'Enter a 10 digit mobile number.',
        ];
    }
}
