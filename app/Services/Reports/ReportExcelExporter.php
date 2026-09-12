<?php

namespace App\Services\Reports;

use ZipArchive;

class ReportExcelExporter
{
    public function contents(ReportExportDocument $document): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'report-xlsx-');

        if ($tmp === false) {
            throw new \RuntimeException('Unable to create a temporary Excel file.');
        }

        $xlsx = $tmp.'.xlsx';
        rename($tmp, $xlsx);

        $zip = new ZipArchive;

        if ($zip->open($xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the Excel workbook.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($document));
        $zip->close();

        $contents = file_get_contents($xlsx);
        @unlink($xlsx);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read the Excel workbook.');
        }

        return $contents;
    }

    private function contentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private function rels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Report" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function workbookRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function styles(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="4">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
    <xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>
  </cellXfs>
</styleSheet>
XML;
    }

    private function sheet(ReportExportDocument $document): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $rowNumber = 1;
        $xml .= $this->rowXml($rowNumber++, [$document->title], [], [], true);

        foreach ($document->meta as $line) {
            $xml .= $this->rowXml($rowNumber++, [$line], [], [], false);
        }

        $xml .= $this->rowXml($rowNumber++, [''], [], [], false);
        $xml .= $this->rowXml($rowNumber++, $document->headers, [], [], true);

        foreach ($document->rows as $row) {
            $xml .= $this->rowXml($rowNumber++, $row, $document->moneyColumns, $document->integerColumns, false);
        }

        if ($document->totals !== null) {
            $xml .= $this->rowXml($rowNumber, $document->totals, $document->moneyColumns, $document->integerColumns, true);
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  list<string|int|float|null>  $values
     * @param  list<int>  $moneyColumns
     * @param  list<int>  $integerColumns
     */
    private function rowXml(int $rowNumber, array $values, array $moneyColumns, array $integerColumns, bool $bold): string
    {
        $cells = '';

        foreach (array_values($values) as $index => $value) {
            $ref = $this->columnLetter($index).$rowNumber;
            $isMoney = in_array($index, $moneyColumns, true);
            $isInteger = in_array($index, $integerColumns, true);

            if (($isMoney || $isInteger) && is_numeric($value)) {
                $style = $isMoney ? ($bold ? 3 : 2) : ($bold ? 1 : 0);
                $cells .= '<c r="'.$ref.'" s="'.$style.'"><v>'.$this->numeric($value).'</v></c>';

                continue;
            }

            $style = $bold ? ' s="1"' : '';
            $cells .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t>'.$this->xml((string) ($value ?? '')).'</t></is></c>';
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;

        while ($index > 0) {
            $index--;
            $letter = chr(($index % 26) + 65).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function numeric(int|float|string $value): string
    {
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (string) $value
            : number_format((float) $value, 2, '.', '');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
