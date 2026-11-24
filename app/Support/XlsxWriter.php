<?php

namespace App\Support;

/**
 * Минимальная запись .xlsx (Office Open XML) без внешних пакетов и без ext-zip:
 * ZIP собирается вручную, файлы хранятся без сжатия (метод 0), CRC — из ядра PHP.
 * Хватает для отчётов (ТЗ 2.10): один лист, жирная шапка, денежный и дробный форматы.
 *
 * Ячейка: скаляр или ['v' => значение, 's' => 'bold'|'money'|'decimal'].
 * Деньги передаются в TMT (тенге / 100) — это формат вывода, хранение остаётся в тенге.
 */
final class XlsxWriter
{
    private const STYLES = ['bold' => 1, 'money' => 2, 'decimal' => 3];

    /** @var list<list<mixed>> */
    private array $rows = [];

    /** @var list<float> */
    private array $widths = [];

    public function __construct(private readonly string $sheetName = 'Report') {}

    /** @param list<mixed> $cells */
    public function addRow(array $cells): self
    {
        $this->rows[] = $cells;

        return $this;
    }

    /** @param list<string> $titles */
    public function addHeader(array $titles): self
    {
        return $this->addRow(array_map(fn ($t) => ['v' => $t, 's' => 'bold'], $titles));
    }

    /** @param list<float> $widths */
    public function setWidths(array $widths): self
    {
        $this->widths = $widths;

        return $this;
    }

    public function toString(): string
    {
        return $this->zip([
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRels(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->sheet(),
        ]);
    }

    private function sheet(): string
    {
        $cols = '';
        foreach ($this->widths as $i => $w) {
            $n = $i + 1;
            $cols .= sprintf('<col min="%d" max="%d" width="%s" customWidth="1"/>', $n, $n, $w);
        }

        $xml = '';
        foreach ($this->rows as $r => $cells) {
            $rowNum = $r + 1;
            $xml .= '<row r="'.$rowNum.'">';
            foreach ($cells as $c => $cell) {
                $ref = self::column($c).$rowNum;
                [$value, $style] = is_array($cell) ? [$cell['v'] ?? null, $cell['s'] ?? null] : [$cell, null];
                $s = $style !== null ? ' s="'.self::STYLES[$style].'"' : '';

                if ($value === null || $value === '') {
                    $xml .= '<c r="'.$ref.'"'.$s.'/>';
                } elseif (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="0.0"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs></styleSheet>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::esc(mb_substr($this->sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    /**
     * ZIP без сжатия: локальные заголовки + центральный каталог + конец каталога.
     *
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $data = '';
        $directory = '';
        [$time, $date] = self::dosTime();

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $offset = strlen($data);

            $data .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0)
                .$name.$content;

            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset)
                .$name;
        }

        return $data.$directory
            .pack('VvvvvVVv', 0x06054B50, 0, 0, count($files), count($files), strlen($directory), strlen($data), 0);
    }

    /** @return array{0:int, 1:int} */
    private static function dosTime(): array
    {
        $t = getdate();

        return [
            ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2),
            (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'],
        ];
    }

    private static function column(int $index): string
    {
        $name = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $name = chr(65 + ($i - 1) % 26).$name;
        }

        return $name;
    }

    private static function esc(string $value): string
    {
        // Управляющие символы недопустимы в XML 1.0.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
