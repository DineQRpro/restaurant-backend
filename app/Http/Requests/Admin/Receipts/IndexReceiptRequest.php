<?php

namespace App\Http\Requests\Admin\Receipts;

use Illuminate\Foundation\Http\FormRequest;

class IndexReceiptRequest extends FormRequest
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
            'sdate' => ['sometimes', 'nullable', 'date'],
            'edate' => ['sometimes', 'nullable', 'date'],
            'receipt_no' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array{sdate: string, edate: string, receipt_no: string}
     */
    public function filters(): array
    {
        $hasDateParams = $this->has('sdate') || $this->has('edate') || $this->has('receipt_no');

        $sdate = trim((string) $this->input('sdate', ''));
        $edate = trim((string) $this->input('edate', ''));

        if (! $hasDateParams) {
            $sdate = now()->startOfMonth()->toDateString();
            $edate = now()->endOfMonth()->toDateString();
        }

        return [
            'sdate' => $sdate,
            'edate' => $edate,
            'receipt_no' => trim((string) $this->input('receipt_no', '')),
        ];
    }
}
