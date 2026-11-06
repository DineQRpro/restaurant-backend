<?php

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'shop_name' => $this->blankToNull($this->input('shop_name')),
            'poc_name' => $this->blankToNull($this->input('poc_name')),
            'phone' => $this->blankToNull($this->input('phone')),
            'address' => $this->blankToNull($this->input('address')),
            'gst_number' => $this->blankToNull($this->input('gst_number')),
            'bank_name' => $this->blankToNull($this->input('bank_name')),
            'bank_account_number' => $this->blankToNull($this->input('bank_account_number')),
            'ifsc_code' => $this->normalizeIfsc($this->input('ifsc_code')),
            'notes' => $this->blankToNull($this->input('notes')),
            'is_active' => $this->boolean('is_active'),
        ]);

        $this->offsetUnset('code');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'shop_name' => ['required', 'string', 'max:150'],
            'poc_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'gst_number' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:34', 'regex:/^[0-9A-Za-z]+$/'],
            'ifsc_code' => ['nullable', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_name.required' => 'Shop name is required.',
            'poc_name.required' => 'POC name is required.',
            'ifsc_code.regex' => 'Enter a valid IFSC code, such as SBIN0001234.',
            'bank_account_number.regex' => 'Bank account number can contain only letters and digits.',
        ];
    }

    private function normalizeIfsc(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        return $value === null ? null : mb_strtoupper($value);
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
