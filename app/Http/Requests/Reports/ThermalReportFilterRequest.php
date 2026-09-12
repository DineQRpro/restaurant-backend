<?php

namespace App\Http\Requests\Reports;

use App\Services\Reports\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ThermalReportFilterRequest extends FormRequest
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
            'edate' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sdate = trim((string) $this->input('sdate', ''));
            $edate = trim((string) $this->input('edate', ''));

            if ($sdate === '' && $edate === '') {
                return;
            }

            $dates = app(ReportDateRange::class);
            $from = $dates->normalizeStart($sdate !== '' ? $sdate : $edate);
            $to = $dates->normalizeEnd($edate !== '' ? $edate : $sdate);

            if ($from === null || $to === null) {
                $validator->errors()->add('sdate', 'Invalid date & time range.');
            }
        });
    }

    /**
     * @return array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     from_str: string,
     *     to_str: string,
     *     report_date_display: string,
     *     from_display: string,
     *     to_display: string,
     *     is_filtered: bool,
     *     sdate: string,
     *     edate: string
     * }
     */
    public function range(ReportDateRange $dates): array
    {
        $sdate = trim((string) $this->input('sdate', ''));
        $edate = trim((string) $this->input('edate', ''));
        $isFiltered = $sdate !== '' || $edate !== '';

        if (! $isFiltered) {
            return array_merge($dates->last36Hours(), [
                'is_filtered' => false,
                'sdate' => '',
                'edate' => '',
            ]);
        }

        $from = $dates->normalizeStart($sdate !== '' ? $sdate : $edate);
        $to = $dates->normalizeEnd($edate !== '' ? $edate : $sdate);

        if ($from === null || $to === null) {
            return array_merge($dates->last36Hours(), [
                'is_filtered' => false,
                'sdate' => $sdate,
                'edate' => $edate,
            ]);
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $window = $dates->thermalWindow($from, $to);

        return array_merge($window, [
            'is_filtered' => true,
            'sdate' => $from->format('Y-m-d\TH:i'),
            'edate' => $to->format('Y-m-d\TH:i'),
        ]);
    }
}
