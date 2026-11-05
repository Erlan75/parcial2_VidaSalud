<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generador Nativo de Hojas de Cálculo Microsoft Excel (.xlsx OpenXML y .csv UTF-8 con BOM)
 * Sin dependencias externas de Composer. Construye paquetes OpenXML (ECMA-376) en memoria
 * con estilos corporativos Clínica Vida Salud, cabeceras de filtros, usuario emisor y fila de totales.
 */
final class ExcelGenerator
{
    /**
     * Construye un nombre de archivo estandarizado y explícito con fecha/hora y extensión.
     * Ejemplo: Reporte_Citas_Por_Especialidad_20261104_2145.xlsx
     */
    public static function buildFilename(string $baseName, string $extension, ?string $timestamp = null): string
    {
        $ts = $timestamp ?? date('Ymd_Hi');
        $clean = self::slugifyReportName($baseName);
        if (!str_starts_with(strtolower($clean), 'reporte_')) {
            $clean = 'Reporte_' . $clean;
        }
        $ext = ltrim(strtolower(trim($extension)), '.');
        return "{$clean}_{$ts}.{$ext}";
    }

    /**
     * Normaliza un título o identificador a formato Pascal_Snake_Case limpio para nombres de archivo.
     */
    public static function slugifyReportName(string $input): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ];
        $str = strtr(trim($input), $map);
        $str = preg_replace('/^Reporte\s*\d*\s*:\s*/i', '', $str) ?? $str;
        $str = preg_replace('/[^A-Za-z0-9]+/', '_', $str) ?? 'Ejecutivo';
        $str = trim($str, '_');
        if ($str === '') {
            return 'General';
        }
        $parts = array_filter(explode('_', $str), static fn($w) => $w !== '');
        $parts = array_map(static fn($w) => ucfirst(strtolower($w)), array_slice($parts, 0, 7));
        return implode('_', $parts);
    }

    /**
     * Genera un archivo nativo .xlsx (OpenXML ECMA-376) en binario listo para descarga directa.
     *
     * @param string $title       Título exacto del reporte
     * @param string $filtersText Resumen de filtros aplicados
     * @param array  $columns     [['key' => '...', 'label' => '...', 'width' => 15], ...]
     * @param array  $rows        Filas asociativas de datos
     * @param string $generatedBy Nombre y rol del usuario emisor
     * @param array  $extraMeta   Metadatos opcionales adicionales (ej. SQL generado en IA)
     */
    public static function buildXlsx(
        string $title,
        string $filtersText,
        array $columns,
        array $rows,
        string $generatedBy = 'Administrador',
        array $extraMeta = []
    ): string {
        $emittedAt = date('d/m/Y H:i:s');
        $colCount = max(1, count($columns));
        $lastColLetter = self::colLetter($colCount);

        $sheetRowsXml = [];
        $mergeCells = [];
        $rowNum = 1;

        // Fila 1: Membrete Institucional
        $sheetRowsXml[] = self::buildBannerRow(
            $rowNum,
            'CLÍNICA VIDA SALUD · SISTEMA DE GESTIÓN DE CITAS MÉDICAS Y ANALÍTICA',
            $colCount,
            1,
            24
        );
        $mergeCells[] = "A{$rowNum}:{$lastColLetter}{$rowNum}";
        $rowNum++;

        // Fila 2: Título Exacto del Reporte
        $sheetRowsXml[] = self::buildBannerRow(
            $rowNum,
            'REPORTE: ' . mb_strtoupper($title, 'UTF-8'),
            $colCount,
            2,
            22
        );
        $mergeCells[] = "A{$rowNum}:{$lastColLetter}{$rowNum}";
        $rowNum++;

        // Fila 3: Filtros Aplicados
        $sheetRowsXml[] = self::buildMetaRow(
            $rowNum,
            'Filtros Aplicados: ' . ($filtersText !== '' ? $filtersText : 'Histórico General Consolidado'),
            $colCount
        );
        $mergeCells[] = "A{$rowNum}:{$lastColLetter}{$rowNum}";
        $rowNum++;

        // Fila 4: Fecha de Emisión y Usuario Emisor
        $sheetRowsXml[] = self::buildMetaRow(
            $rowNum,
            "Fecha de Emisión: {$emittedAt} (America/La_Paz)   |   Usuario Emisor: {$generatedBy}   |   Total Registros: " . count($rows),
            $colCount
        );
        $mergeCells[] = "A{$rowNum}:{$lastColLetter}{$rowNum}";
        $rowNum++;

        // Fila opcional de información adicional (ej. instrucción de voz o SQL en Tipo 3)
        if (!empty($extraMeta['subtitle_extra'])) {
            $sheetRowsXml[] = self::buildMetaRow(
                $rowNum,
                (string) $extraMeta['subtitle_extra'],
                $colCount
            );
            $mergeCells[] = "A{$rowNum}:{$lastColLetter}{$rowNum}";
            $rowNum++;
        }

        // Fila en blanco separadora
        $sheetRowsXml[] = "<row r=\"{$rowNum}\" ht=\"10\" customHeight=\"1\"/>";
        $rowNum++;

        // Fila de Encabezados de Columnas
        $headerCells = [];
        foreach (array_values($columns) as $idx => $col) {
            $cellRef = self::colLetter($idx + 1) . $rowNum;
            $label = mb_strtoupper((string) ($col['label'] ?? $col['key'] ?? ''), 'UTF-8');
            $headerCells[] = self::inlineStrCell($cellRef, $label, 4);
        }
        $sheetRowsXml[] = "<row r=\"{$rowNum}\" ht=\"22\" customHeight=\"1\">" . implode('', $headerCells) . "</row>";
        $rowNum++;

        // Acumulador para fila de totales al pie
        $numericTotals = array_fill(0, count($columns), null);

        // Filas de Datos
        if (empty($rows)) {
            $emptyCells = [self::inlineStrCell("A{$rowNum}", 'No se encontraron registros para los criterios seleccionados.', 5)];
            for ($c = 2; $c <= $colCount; $c++) {
                $emptyCells[] = self::inlineStrCell(self::colLetter($c) . $rowNum, '', 5);
            }
            $sheetRowsXml[] = "<row r=\"{$rowNum}\" ht=\"20\" customHeight=\"1\">" . implode('', $emptyCells) . "</row>";
            $mergeCells[] = "A{$rowNum}:{$lastColLetter}{$rowNum}";
            $rowNum++;
        } else {
            foreach ($rows as $rIdx => $row) {
                $styleIdx = ($rIdx % 2 === 0) ? 5 : 6;
                $cellsXml = [];

                foreach (array_values($columns) as $cIdx => $col) {
                    $key = (string) ($col['key'] ?? '');
                    $rawVal = $row[$key] ?? '';
                    $valStr = trim((string) $rawVal);
                    $cellRef = self::colLetter($cIdx + 1) . $rowNum;

                    // Detectar si es columna numérica pura (no CI ni teléfono ni código)
                    $isCodeOrId = (bool) preg_match('/(codigo|ci|telefono|matricula|registro|fecha|hora|edad)/i', $key);
                    if (!$isCodeOrId && is_numeric($valStr) && !str_starts_with($valStr, '0')) {
                        $numVal = (float) $valStr;
                        $numericTotals[$cIdx] = ($numericTotals[$cIdx] ?? 0.0) + $numVal;
                        $cellsXml[] = "<c r=\"{$cellRef}\" s=\"{$styleIdx}\"><v>{$numVal}</v></c>";
                    } else {
                        $cellsXml[] = self::inlineStrCell($cellRef, $valStr !== '' ? $valStr : '-', $styleIdx);
                    }
                }

                $sheetRowsXml[] = "<row r=\"{$rowNum}\" ht=\"19\" customHeight=\"1\">" . implode('', $cellsXml) . "</row>";
                $rowNum++;
            }
        }

        // Fila de Totales al Pie
        $totalCells = [];
        foreach (array_values($columns) as $cIdx => $col) {
            $cellRef = self::colLetter($cIdx + 1) . $rowNum;
            if ($cIdx === 0) {
                $totalCells[] = self::inlineStrCell($cellRef, 'TOTAL REGISTROS: ' . count($rows), 7);
            } elseif ($numericTotals[$cIdx] !== null) {
                $sumVal = $numericTotals[$cIdx];
                $formattedSum = floor($sumVal) == $sumVal ? (string) (int) $sumVal : number_format($sumVal, 2, '.', '');
                $totalCells[] = self::inlineStrCell($cellRef, 'Σ = ' . $formattedSum, 7);
            } else {
                $totalCells[] = self::inlineStrCell($cellRef, $cIdx === count($columns) - 1 ? 'FIN DEL REPORTE' : '', 7);
            }
        }
        $sheetRowsXml[] = "<row r=\"{$rowNum}\" ht=\"22\" customHeight=\"1\">" . implode('', $totalCells) . "</row>";

        // Definición de anchos de columnas
        $colsXmlParts = [];
        foreach (array_values($columns) as $idx => $col) {
            $colIndex = $idx + 1;
            $w = max(14, min(48, (int) (($col['width'] ?? 15) * 1.35)));
            $colsXmlParts[] = "<col min=\"{$colIndex}\" max=\"{$colIndex}\" width=\"{$w}\" customWidth=\"1\"/>";
        }
        $colsXml = '<cols>' . implode('', $colsXmlParts) . '</cols>';

        $mergeXml = '';
        if (!empty($mergeCells)) {
            $mergeItems = array_map(static fn($ref) => "<mergeCell ref=\"{$ref}\"/>", $mergeCells);
            $mergeXml = '<mergeCells count="' . count($mergeItems) . '">' . implode('', $mergeItems) . '</mergeCells>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="1"/></sheetViews>'
            . $colsXml
            . '<sheetData>' . implode('', $sheetRowsXml) . '</sheetData>'
            . $mergeXml
            . '</worksheet>';

        $files = [
            '[Content_Types].xml'        => self::getContentTypesXml(),
            '_rels/.rels'                => self::getRootRelsXml(),
            'xl/workbook.xml'            => self::getWorkbookXml('Reporte Vida Salud'),
            'xl/_rels/workbook.xml.rels' => self::getWorkbookRelsXml(),
            'xl/styles.xml'              => self::getStylesXml(),
            'xl/worksheets/sheet1.xml'   => $sheetXml,
        ];

        return self::buildZipArchiveBytes($files);
    }

    /**
     * Genera un archivo CSV compatible con Microsoft Excel (UTF-8 con BOM, delimitador ';')
     * incluyendo cabecera institucional, filtros, fecha de emisión, usuario emisor y fila de totales.
     */
    public static function buildCsv(
        string $title,
        string $filtersText,
        array $columns,
        array $rows,
        string $generatedBy = 'Administrador',
        array $extraMeta = []
    ): string {
        $emittedAt = date('d/m/Y H:i:s');
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, ['CLÍNICA VIDA SALUD - SISTEMA DE GESTIÓN DE CITAS MÉDICAS'], ';');
        fputcsv($stream, ['REPORTE: ' . mb_strtoupper($title, 'UTF-8')], ';');
        fputcsv($stream, ['Filtros Aplicados: ' . ($filtersText !== '' ? $filtersText : 'Histórico General Consolidado')], ';');
        fputcsv($stream, ['Fecha de Emisión: ' . $emittedAt, 'Usuario Emisor: ' . $generatedBy, 'Total Registros: ' . count($rows)], ';');
        if (!empty($extraMeta['subtitle_extra'])) {
            fputcsv($stream, [(string) $extraMeta['subtitle_extra']], ';');
        }
        fputcsv($stream, [], ';');

        $headers = array_map(static fn($c) => (string) ($c['label'] ?? $c['key'] ?? ''), $columns);
        fputcsv($stream, $headers, ';');

        $numericTotals = array_fill(0, count($columns), null);

        foreach ($rows as $row) {
            $line = [];
            foreach (array_values($columns) as $cIdx => $col) {
                $key = (string) ($col['key'] ?? '');
                $val = trim((string) ($row[$key] ?? ''));
                $line[] = $val;

                $isCodeOrId = (bool) preg_match('/(codigo|ci|telefono|matricula|registro|fecha|hora|edad)/i', $key);
                if (!$isCodeOrId && is_numeric($val) && !str_starts_with($val, '0')) {
                    $numericTotals[$cIdx] = ($numericTotals[$cIdx] ?? 0.0) + (float) $val;
                }
            }
            fputcsv($stream, $line, ';');
        }

        fputcsv($stream, [], ';');
        $footerRow = [];
        foreach (array_values($columns) as $cIdx => $col) {
            if ($cIdx === 0) {
                $footerRow[] = 'TOTAL REGISTROS: ' . count($rows);
            } elseif ($numericTotals[$cIdx] !== null) {
                $sumVal = $numericTotals[$cIdx];
                $footerRow[] = 'TOTAL: ' . (floor($sumVal) == $sumVal ? (string) (int) $sumVal : number_format($sumVal, 2, '.', ''));
            } else {
                $footerRow[] = '';
            }
        }
        fputcsv($stream, $footerRow, ';');

        rewind($stream);
        $csvBytes = (string) stream_get_contents($stream);
        fclose($stream);

        return $csvBytes;
    }

    /**
     * Calcula una fila resumen de totales para reutilizar también en PdfGenerator o vistas.
     */
    public static function computeTotalsRow(array $columns, array $rows): array
    {
        $totals = [];
        foreach (array_values($columns) as $cIdx => $col) {
            $key = (string) ($col['key'] ?? '');
            if ($cIdx === 0) {
                $totals[$key] = 'TOTAL: ' . count($rows) . ' reg.';
                continue;
            }
            $isCodeOrId = (bool) preg_match('/(codigo|ci|telefono|matricula|registro|fecha|hora|edad)/i', $key);
            $sum = null;
            if (!$isCodeOrId) {
                foreach ($rows as $r) {
                    $v = trim((string) ($r[$key] ?? ''));
                    if ($v !== '' && is_numeric($v) && !str_starts_with($v, '0')) {
                        $sum = ($sum ?? 0.0) + (float) $v;
                    }
                }
            }
            if ($sum !== null) {
                $totals[$key] = floor($sum) == $sum ? (string) (int) $sum : number_format($sum, 2, '.', '');
            } else {
                $totals[$key] = ($cIdx === count($columns) - 1) ? '100% Consolidado' : '-';
            }
        }
        return $totals;
    }

    private static function buildBannerRow(int $rowNum, string $text, int $colCount, int $styleIdx, int $height = 22): string
    {
        $cells = [self::inlineStrCell("A{$rowNum}", $text, $styleIdx)];
        for ($c = 2; $c <= $colCount; $c++) {
            $cells[] = self::inlineStrCell(self::colLetter($c) . $rowNum, '', $styleIdx);
        }
        return "<row r=\"{$rowNum}\" ht=\"{$height}\" customHeight=\"1\">" . implode('', $cells) . "</row>";
    }

    private static function buildMetaRow(int $rowNum, string $text, int $colCount): string
    {
        $cells = [self::inlineStrCell("A{$rowNum}", $text, 3)];
        for ($c = 2; $c <= $colCount; $c++) {
            $cells[] = self::inlineStrCell(self::colLetter($c) . $rowNum, '', 3);
        }
        return "<row r=\"{$rowNum}\" ht=\"18\" customHeight=\"1\">" . implode('', $cells) . "</row>";
    }

    private static function inlineStrCell(string $cellRef, string $value, int $styleIdx): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;
        $escaped = htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return "<c r=\"{$cellRef}\" t=\"inlineStr\" s=\"{$styleIdx}\"><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
    }

    private static function colLetter(int $colNumber): string
    {
        $dividend = max(1, $colNumber);
        $columnName = '';
        while ($dividend > 0) {
            $modulo = ($dividend - 1) % 26;
            $columnName = chr(65 + $modulo) . $columnName;
            $dividend = (int) (($dividend - $modulo) / 26);
        }
        return $columnName;
    }

    private static function getContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function getRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function getWorkbookXml(string $sheetName): string
    {
        $safeName = htmlspecialchars(mb_substr($sheetName, 0, 31, 'UTF-8'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $safeName . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function getWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /**
     * Estilos OpenXML:
     * 0: Default
     * 1: Banner Navy (#002D62) texto blanco Bold 12pt
     * 2: Subbanner Azul (#1E4E8C) texto blanco Bold 11pt
     * 3: Meta fila (#EEF5FC) texto gris oscuro 9.5pt
     * 4: Cabecera de tabla (#2B579A) texto blanco Bold 10pt con bordes
     * 5: Celda de datos par (#FFFFFF) con bordes finos
     * 6: Celda de datos impar (#F8FAFC) con bordes finos
     * 7: Fila de totales al pie (#D4E6F7) texto Navy Bold 10pt con borde superior doble
     */
    private static function getStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="6">'
            . '<font><sz val="10"/><color rgb="FF1E293B"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFD4E6F7"/><name val="Calibri"/></font>'
            . '<font><sz val="9.5"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF002D62"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="8">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF002D62"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E4E8C"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF5FC"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2B579A"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD4E6F7"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="3">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
            . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '<border>'
            . '<left style="thin"><color rgb="FF2B579A"/></left>'
            . '<right style="thin"><color rgb="FF2B579A"/></right>'
            . '<top style="medium"><color rgb="FF002D62"/></top>'
            . '<bottom style="double"><color rgb="FF002D62"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    /**
     * Empaqueta entradas XML en un contenedor ZIP estándar (PKZIP 2.0) en memoria pura PHP,
     * sin requerir la extensión ext-zip instalada en el sistema operativo.
     *
     * @param array<string, string> $entries
     */
    private static function buildZipArchiveBytes(array $entries): string
    {
        $localParts = [];
        $centralParts = [];
        $offset = 0;
        $count = 0;

        foreach ($entries as $filename => $content) {
            $filenameBytes = str_replace('\\', '/', $filename);
            $nameLen = strlen($filenameBytes);
            $uncompressedSize = strlen($content);
            $crc = crc32($content);
            if ($crc < 0) {
                $crc += 4294967296;
            }

            $compressed = gzdeflate($content, 6);
            if ($compressed === false) {
                $method = 0;
                $compressed = $content;
            } else {
                $method = 8;
            }
            $compressedSize = strlen($compressed);

            // Local file header (PK\x03\x04)
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                $method,
                0,
                0x5821,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0
            ) . $filenameBytes;

            $localEntry = $localHeader . $compressed;
            $localParts[] = $localEntry;

            // Central directory file header (PK\x01\x02)
            $centralParts[] = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                $method,
                0,
                0x5821,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0,
                0,
                0,
                0,
                32,
                $offset
            ) . $filenameBytes;

            $offset += strlen($localEntry);
            $count++;
        }

        $centralDir = implode('', $centralParts);
        $centralSize = strlen($centralDir);

        // End of central directory record (PK\x05\x06)
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $offset,
            0
        );

        return implode('', $localParts) . $centralDir . $eocd;
    }
}

