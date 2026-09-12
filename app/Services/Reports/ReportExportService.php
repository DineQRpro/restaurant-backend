<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\Response;

class ReportExportService
{
    public function __construct(
        private readonly ReportCsvExporter $csv,
        private readonly ReportExcelExporter $excel,
        private readonly ReportPdfExporter $pdf,
        private readonly ReportFilename $filenames,
    ) {}

    public function download(ReportExportDocument $document, ReportFilters $filters, string $format): Response
    {
        return match ($format) {
            'excel' => $this->excelDownload($document, $filters),
            'pdf' => $this->pdfDownload($document, $filters),
            default => $this->csv->downloadDocument(
                $document,
                $this->filenames->make($document, $filters, 'csv'),
            ),
        };
    }

    private function excelDownload(ReportExportDocument $document, ReportFilters $filters): Response
    {
        $filename = $this->filenames->make($document, $filters, 'xlsx');
        $contents = $this->excel->contents($document);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
        ]);
    }

    private function pdfDownload(ReportExportDocument $document, ReportFilters $filters): Response
    {
        $filename = $this->filenames->make($document, $filters, 'pdf');
        $contents = $this->pdf->contents($document);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
        ]);
    }
}
