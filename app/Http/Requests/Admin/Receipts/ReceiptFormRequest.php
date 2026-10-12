<?php

namespace App\Http\Requests\Admin\Receipts;

use App\Models\Receipt;
use Illuminate\Foundation\Http\FormRequest;

class ReceiptFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mobile = preg_replace('/\s+/', '', (string) $this->input('mobile', ''));

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name', '')),
            'mobile' => $mobile,
            'program' => trim((string) $this->input('program', '')),
            'other_instructions' => trim((string) $this->input('other_instructions', '')),
            'include_gst' => $this->boolean('include_gst'),
            'items' => $this->zipItems(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date', 'date_format:Y-m-d'],
            'customer_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'program' => ['required', 'string'],
            'other_instructions' => ['nullable', 'string'],
            'pax_count' => ['required', 'integer', 'min:1'],
            'advance_paid' => ['required', 'numeric', 'min:0'],
            'balance' => ['required', 'numeric'],
            'include_gst' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:'.Receipt::MAX_ITEMS],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rate_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_date.required' => 'The receipt date field is required.',
            'receipt_date.date_format' => 'Enter a valid date.',
            'customer_name.required' => 'The customer name field is required.',
            'mobile.required' => 'The mobile field is required.',
            'mobile.regex' => 'Mobile number must be exactly 10 digits.',
            'program.required' => 'The program field is required.',
            'pax_count.required' => 'The PAX count field is required.',
            'pax_count.integer' => 'PAX count must be a positive number.',
            'pax_count.min' => 'PAX count must be a positive number.',
            'advance_paid.required' => 'Advance Paid is required.',
            'advance_paid.numeric' => 'Advance Paid must be a valid amount.',
            'advance_paid.min' => 'Advance Paid must be a valid amount.',
            'balance.required' => 'Balance is required.',
            'balance.numeric' => 'Balance must be a valid amount.',
            'items.required' => 'Please add at least one item.',
            'items.min' => 'Please add at least one item.',
            'items.max' => 'Maximum 10 item rows are allowed.',
            'items.*.item_name.required' => 'Item Name, Quantity and Rate Price are required for each filled row.',
            'items.*.quantity.required' => 'Item Name, Quantity and Rate Price are required for each filled row.',
            'items.*.quantity.numeric' => 'Quantity must be a positive number.',
            'items.*.quantity.gt' => 'Quantity must be a positive number.',
            'items.*.rate_price.required' => 'Item Name, Quantity and Rate Price are required for each filled row.',
            'items.*.rate_price.numeric' => 'Rate Price must be a valid amount.',
            'items.*.rate_price.min' => 'Rate Price must be a valid amount.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $instructions = trim((string) ($validated['other_instructions'] ?? ''));

        return [
            'receipt_date' => $validated['receipt_date'],
            'customer_name' => $validated['customer_name'],
            'mobile' => $validated['mobile'],
            'program' => $validated['program'],
            'other_instructions' => $instructions !== '' ? $instructions : null,
            'pax_count' => (int) $validated['pax_count'],
            'advance_paid' => (float) $validated['advance_paid'],
            'balance' => (float) $validated['balance'],
            'include_gst' => (bool) ($validated['include_gst'] ?? false),
        ];
    }

    /**
     * @return list<array{item_name: string, quantity: float, rate_price: float}>
     */
    public function itemsPayload(): array
    {
        $items = [];

        foreach ($this->validated('items') as $item) {
            $items[] = [
                'item_name' => $item['item_name'],
                'quantity' => (float) $item['quantity'],
                'rate_price' => (float) $item['rate_price'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array{item_name: string, quantity: string, rate_price: string}>
     */
    private function zipItems(): array
    {
        $names = $this->input('item_name', []);
        $quantities = $this->input('quantity', []);
        $rates = $this->input('rate_price', []);

        if (! is_array($names)) {
            $names = [];
        }
        if (! is_array($quantities)) {
            $quantities = [];
        }
        if (! is_array($rates)) {
            $rates = [];
        }

        $rowCount = max(count($names), count($quantities), count($rates));
        $items = [];

        for ($i = 0; $i < $rowCount; $i++) {
            $name = trim((string) ($names[$i] ?? ''));
            $qty = trim((string) ($quantities[$i] ?? ''));
            $rate = trim((string) ($rates[$i] ?? ''));

            if ($name === '' && $qty === '' && $rate === '') {
                continue;
            }

            $items[] = [
                'item_name' => $name,
                'quantity' => $qty,
                'rate_price' => $rate,
            ];
        }

        return $items;
    }
}
