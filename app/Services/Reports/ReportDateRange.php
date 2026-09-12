<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReportDateRange
{
    /**
     * Legacy getDefaultReportDateRange(): today-9 days 00:00 through today 23:59.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, sdate: string, edate: string}
     */
    public function defaultRange(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $from = $now->subDays(9)->startOfDay();
        $to = $now->setTime(23, 59, 0);

        return [
            'from' => $from,
            'to' => $to,
            'sdate' => $from->format('Y-m-d\TH:i'),
            'edate' => $to->format('Y-m-d\TH:i'),
        ];
    }

    /**
     * Legacy getLast36HourRange(): now-36 hours through now.
     *
     * @return array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     from_str: string,
     *     to_str: string,
     *     report_date_display: string,
     *     from_display: string,
     *     to_display: string
     * }
     */
    public function last36Hours(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $from = $now->subHours(36);

        return $this->thermalWindow($from, $now);
    }

    /**
     * @return array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     from_str: string,
     *     to_str: string,
     *     report_date_display: string,
     *     from_display: string,
     *     to_display: string
     * }
     */
    public function thermalWindow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'from_str' => $from->format('Y-m-d H:i:s'),
            'to_str' => $to->format('Y-m-d H:i:s'),
            'report_date_display' => $to->format('d/m/Y'),
            'from_display' => $from->format('d/m/Y h:i A'),
            'to_display' => $to->format('d/m/Y h:i A'),
        ];
    }

    public function normalizeStart(?string $value): ?CarbonImmutable
    {
        return $this->normalize($value, false);
    }

    public function normalizeEnd(?string $value): ?CarbonImmutable
    {
        return $this->normalize($value, true);
    }

    /**
     * COALESCE(issued_at, created_at) — Laravel equivalent of bill_created_at.
     */
    public function billTimestampSql(string $alias = 'bills'): string
    {
        return "COALESCE({$alias}.issued_at, {$alias}.created_at)";
    }

    /**
     * Calendar date of the bill timestamp, portable across MySQL and SQLite.
     */
    public function billCalendarDateSql(string $alias = 'bills'): string
    {
        $column = $this->billTimestampSql($alias);

        return DB::connection()->getDriverName() === 'sqlite'
            ? "date({$column})"
            : "DATE({$column})";
    }

    public function orderTimestampSql(string $alias = 'orders'): string
    {
        return "COALESCE({$alias}.started_at, {$alias}.created_at)";
    }

    private function normalize(?string $value, bool $isEnd): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $value);

            return $isEnd
                ? $parsed->setTime(23, 59, 59)
                : $parsed->startOfDay();
        }

        $value = str_replace('T', ' ', $value);
        $parsed = CarbonImmutable::parse($value);

        if ($isEnd && $parsed->format('H:i:s') === '00:00:00') {
            return $parsed->setTime(23, 59, 59);
        }

        return $parsed;
    }
}
