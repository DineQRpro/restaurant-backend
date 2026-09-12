<?php

namespace App\Services\Reports;

class ReportExportDocument
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<string|int|float|null>|null  $totals
     * @param  list<string>  $meta
     * @param  list<int>  $moneyColumns
     * @param  list<int>  $integerColumns
     */
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly array $headers,
        public readonly array $rows,
        public readonly ?array $totals = null,
        public readonly array $meta = [],
        public readonly array $moneyColumns = [],
        public readonly array $integerColumns = [],
        public readonly bool $landscape = true,
    ) {}
}
