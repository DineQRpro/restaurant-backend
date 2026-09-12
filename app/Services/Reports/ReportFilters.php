<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

class ReportFilters
{
    public function __construct(
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly ?string $billNo = null,
        public readonly ?string $billStatus = null,
        public readonly ?string $orderType = null,
        public readonly ?string $itemName = null,
        public readonly bool $showAll = false,
        public readonly ?string $exportFormat = null,
        public readonly string $sdate = '',
        public readonly string $edate = '',
    ) {}

    public function hasDateRange(): bool
    {
        return $this->from !== null && $this->to !== null;
    }

    public function isExport(): bool
    {
        return in_array($this->exportFormat, ['csv', 'excel', 'pdf'], true);
    }

    /**
     * @return array<string, string>
     */
    public function queryString(): array
    {
        $query = [];

        if ($this->showAll) {
            $query['show_all'] = '1';
        } else {
            if ($this->sdate !== '') {
                $query['sdate'] = $this->sdate;
            }
            if ($this->edate !== '') {
                $query['edate'] = $this->edate;
            }
        }

        if ($this->billNo !== null && $this->billNo !== '') {
            $query['bill_no'] = $this->billNo;
        }

        if ($this->billStatus !== null && $this->billStatus !== '') {
            $query['bill_status'] = $this->billStatus;
        }

        if ($this->orderType !== null && $this->orderType !== '') {
            $query['order_type'] = $this->orderType;
        }

        if ($this->itemName !== null && $this->itemName !== '') {
            $query['item_name'] = $this->itemName;
        }

        return $query;
    }
}
