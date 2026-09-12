<?php

namespace App\Services\Reports;

class ReportFilename
{
    public function make(ReportExportDocument $document, ReportFilters $filters, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($document->slug)) ?? 'report';
        $slug = trim($slug, '-');

        if ($filters->showAll) {
            $range = 'all';
        } elseif ($filters->from !== null && $filters->to !== null) {
            $from = $filters->from->format('Y-m-d');
            $to = $filters->to->format('Y-m-d');
            $range = $from === $to ? $from : $from.'-to-'.$to;
        } else {
            $range = now()->format('Y-m-d');
        }

        return $slug.'-'.$range.'.'.$extension;
    }
}
