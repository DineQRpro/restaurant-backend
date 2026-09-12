<?php

namespace App\Http\Requests\Reports;

use App\Services\Reports\ReportDateRange;
use App\Services\Reports\ReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
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
            'sdate' => ['nullable', 'date'],
            'edate' => ['nullable', 'date', 'after_or_equal:sdate'],
            'bill_no' => ['nullable', 'string', 'max:50'],
            'bill_status' => ['nullable', 'in:paid,unpaid,credit,cancel,non_chargeable'],
            'order_type' => ['nullable', 'in:through_waiter,dine_in,take_away,candle_light_dinner,couple_dine'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'show_all' => ['nullable', 'in:1,true'],
            'export' => ['nullable', 'in:csv,excel,pdf'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('show_all')) {
                return;
            }

            $hasStart = $this->filled('sdate');
            $hasEnd = $this->filled('edate');

            if ($hasStart xor $hasEnd) {
                $validator->errors()->add('edate', 'Please select both start and end dates.');
            }
        });
    }

    public function filters(ReportDateRange $dates, bool $defaultToRange = true): ReportFilters
    {
        $showAll = $this->boolean('show_all');
        $sdate = trim((string) $this->input('sdate', ''));
        $edate = trim((string) $this->input('edate', ''));

        $from = null;
        $to = null;

        if (! $showAll && $sdate !== '' && $edate !== '') {
            $from = $dates->normalizeStart($sdate);
            $to = $dates->normalizeEnd($edate);
        } elseif (! $showAll && $defaultToRange && $sdate === '' && $edate === '') {
            $default = $dates->defaultRange();
            $from = $default['from'];
            $to = $default['to'];
            $sdate = $default['sdate'];
            $edate = $default['edate'];
        }

        return new ReportFilters(
            from: $from,
            to: $to,
            billNo: trim((string) $this->input('bill_no', '')) ?: null,
            billStatus: trim((string) $this->input('bill_status', '')) ?: null,
            orderType: trim((string) $this->input('order_type', '')) ?: null,
            itemName: trim((string) $this->input('item_name', '')) ?: null,
            showAll: $showAll,
            exportFormat: in_array($this->input('export'), ['csv', 'excel', 'pdf'], true)
                ? (string) $this->input('export')
                : null,
            sdate: $sdate,
            edate: $edate,
        );
    }
}
