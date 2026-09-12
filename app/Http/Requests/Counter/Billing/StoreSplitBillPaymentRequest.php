<?php

namespace App\Http\Requests\Counter\Billing;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSplitBillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payments = $this->input('payments');

        if (! is_array($payments)) {
            return;
        }

        foreach ($payments as $index => $payment) {
            if (! is_array($payment)) {
                continue;
            }

            foreach (['reference_number', 'notes'] as $field) {
                if (($payment[$field] ?? null) === '') {
                    $payments[$index][$field] = null;
                }
            }
        }

        $this->merge(['payments' => $payments]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $excludedIds = PaymentMethod::query()
            ->whereIn('slug', [PaymentMethod::SLUG_SPLIT, PaymentMethod::SLUG_NON_CHARGEABLE])
            ->pluck('id')
            ->all();

        $methodRules = [
            'required',
            'integer',
            Rule::exists('payment_methods', 'id')->where('is_active', true),
            Rule::notIn($excludedIds),
        ];

        return [
            'payments' => ['required', 'array', 'size:2'],
            'payments.0.payment_method_id' => $methodRules,
            'payments.1.payment_method_id' => array_merge($methodRules, [
                'different:payments.0.payment_method_id',
            ]),
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:150'],
            'payments.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
