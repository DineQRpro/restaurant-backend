<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return $this->stream($filename, function ($handle) use ($headers, $rows): void {
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
        });
    }

    public function downloadDocument(ReportExportDocument $document, string $filename): StreamedResponse
    {
        return $this->stream($filename, function ($handle) use ($document): void {
            fputcsv($handle, [$document->title]);

            foreach ($document->meta as $line) {
                fputcsv($handle, [$line]);
            }

            fputcsv($handle, []);
            fputcsv($handle, $document->headers);

            foreach ($document->rows as $row) {
                fputcsv($handle, $row);
            }

            if ($document->totals !== null) {
                fputcsv($handle, $document->totals);
            }
        });
    }

    /**
     * @param  callable(resource): void  $callback
     */
    private function stream(string $filename, callable $callback): StreamedResponse
    {
        return response()->streamDownload(function () use ($callback): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            $callback($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
