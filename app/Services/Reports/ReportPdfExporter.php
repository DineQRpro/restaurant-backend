<?php

namespace App\Services\Reports;

class ReportPdfExporter
{
    public function contents(ReportExportDocument $document): string
    {
        $landscape = $document->landscape;
        $pageWidth = $landscape ? 842 : 595;
        $pageHeight = $landscape ? 595 : 842;
        $margin = 36;
        $usableWidth = $pageWidth - ($margin * 2);
        $columnCount = max(1, count($document->headers));
        $columnWidth = $usableWidth / $columnCount;
        $fontSize = $columnCount > 8 ? 7 : 8;
        $rowHeight = $fontSize + 6;

        $chunks = $this->paginate($document, $pageHeight, $margin, $rowHeight);
        $pageCount = count($chunks);
        $contentIds = [];
        $pageIds = [];

        for ($i = 0; $i < $pageCount; $i++) {
            $contentIds[] = 5 + $i;
            $pageIds[] = 5 + $pageCount + $i;
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = implode(' ', array_map(fn (int $id): string => "{$id} 0 R", $pageIds));
        $objects[2] = "<< /Type /Pages /Count {$pageCount} /Kids [{$kids}] >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        foreach ($chunks as $index => $pageRows) {
            $stream = $this->pageStream(
                $document,
                $pageRows,
                $pageWidth,
                $pageHeight,
                $margin,
                $columnWidth,
                $fontSize,
                $rowHeight,
            );
            $objects[$contentIds[$index]] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageIds[$index]] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] "
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                ."/Contents {$contentIds[$index]} 0 R >>";
        }

        ksort($objects);

        return $this->buildPdf($objects);
    }

    /**
     * @return list<list<array{values: list<string|int|float|null>, bold: bool}>>
     */
    private function paginate(ReportExportDocument $document, int $pageHeight, int $margin, int $rowHeight): array
    {
        $usableHeight = $pageHeight - ($margin * 2);
        $headerHeight = (6 + count($document->meta)) * $rowHeight;
        $rowsPerPage = max(1, (int) floor(($usableHeight - $headerHeight) / $rowHeight));

        $dataRows = [];

        foreach ($document->rows as $row) {
            $dataRows[] = ['values' => $row, 'bold' => false];
        }

        if ($document->totals !== null) {
            $dataRows[] = ['values' => $document->totals, 'bold' => true];
        }

        if ($dataRows === []) {
            return [[]];
        }

        return array_chunk($dataRows, $rowsPerPage);
    }

    /**
     * @param  list<array{values: list<string|int|float|null>, bold: bool}>  $pageRows
     */
    private function pageStream(
        ReportExportDocument $document,
        array $pageRows,
        int $pageWidth,
        int $pageHeight,
        int $margin,
        float $columnWidth,
        int $fontSize,
        int $rowHeight,
    ): string {
        $y = $pageHeight - $margin;
        $commands = ['BT', '/F2 14 Tf', $this->text($margin, $y, 'Varsity Kitchen')];
        $y -= 18;
        $commands[] = '/F2 12 Tf';
        $commands[] = $this->text($margin, $y, $document->title);
        $y -= 16;
        $commands[] = '/F1 8 Tf';

        foreach ($document->meta as $line) {
            $commands[] = $this->text($margin, $y, $line);
            $y -= 12;
        }

        $commands[] = 'ET';
        $y -= 10;

        $commands = array_merge($commands, $this->tableRow(
            $document->headers,
            $margin,
            $y,
            $columnWidth,
            $fontSize,
            true,
            [],
            [],
        ));
        $y -= $rowHeight;

        foreach ($pageRows as $row) {
            $commands = array_merge($commands, $this->tableRow(
                $row['values'],
                $margin,
                $y,
                $columnWidth,
                $fontSize,
                $row['bold'],
                $document->moneyColumns,
                $document->integerColumns,
            ));
            $y -= $rowHeight;
        }

        return implode("\n", $commands);
    }

    /**
     * @param  list<string|int|float|null>  $values
     * @param  list<int>  $moneyColumns
     * @param  list<int>  $integerColumns
     * @return list<string>
     */
    private function tableRow(
        array $values,
        int $margin,
        float $y,
        float $columnWidth,
        int $fontSize,
        bool $bold,
        array $moneyColumns,
        array $integerColumns,
    ): array {
        $font = $bold ? '/F2' : '/F1';
        $commands = ['BT', "{$font} {$fontSize} Tf"];

        foreach (array_values($values) as $index => $value) {
            $text = $this->displayValue($value, in_array($index, $moneyColumns, true), in_array($index, $integerColumns, true));
            $maxChars = max(4, (int) floor($columnWidth / ($fontSize * 0.5)));
            if (strlen($text) > $maxChars) {
                $text = substr($text, 0, $maxChars - 3).'...';
            }
            $x = $margin + ($index * $columnWidth);
            $commands[] = $this->text($x, $y, $text);
        }

        $commands[] = 'ET';

        return $commands;
    }

    private function displayValue(string|int|float|null $value, bool $money, bool $integer): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($money && is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        if ($integer && is_numeric($value)) {
            return (string) (int) $value;
        }

        return (string) $value;
    }

    private function text(float $x, float $y, string $text): string
    {
        return sprintf('1 0 0 1 %.2F %.2F Tm (%s) Tj', $x, $y, $this->escape($text));
    }

    private function escape(string $text): string
    {
        $converted = function_exists('iconv')
            ? @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text)
            : false;

        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }

    /**
     * @param  array<int, string>  $objects
     */
    private function buildPdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
