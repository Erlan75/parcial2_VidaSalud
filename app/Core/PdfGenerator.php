<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Motor PDF 1.4 Nativo en PHP Puro - Clínica Vida Salud (CU15)
 * Soporta documentos multipágina A4 vertical/horizontal, tipografías Type1 WinAnsi (acentos españoles),
 * gráficos vectoriales institucionales y renderizado vectorial directo de códigos QR.
 */
final class PdfGenerator
{
    private float $pageWidth;
    private float $pageHeight;
    private array $pages = [];
    private int $currentPage = -1;

    public function __construct(string $orientation = 'P')
    {
        if (strtoupper($orientation) === 'L') {
            $this->pageWidth = 841.89;
            $this->pageHeight = 595.28;
        } else {
            $this->pageWidth = 595.28;
            $this->pageHeight = 841.89;
        }
    }

    public function addPage(): void
    {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
    }

    public function getWidth(): float
    {
        return $this->pageWidth;
    }

    public function getHeight(): float
    {
        return $this->pageHeight;
    }

    public function rect(float $x, float $yTop, float $w, float $h, string $fillHex = '', string $strokeHex = '', float $lineWidth = 0.6): void
    {
        $y = $this->pageHeight - $yTop - $h;
        $ops = [];
        if ($lineWidth > 0) {
            $ops[] = sprintf('%.2f w', $lineWidth);
        }
        if ($fillHex !== '') {
            [$r, $g, $b] = $this->hexToRgb($fillHex);
            $ops[] = sprintf('%.3f %.3f %.3f rg', $r, $g, $b);
        }
        if ($strokeHex !== '') {
            [$r, $g, $b] = $this->hexToRgb($strokeHex);
            $ops[] = sprintf('%.3f %.3f %.3f RG', $r, $g, $b);
        }

        $mode = 'n';
        if ($fillHex !== '' && $strokeHex !== '') {
            $mode = 'B';
        } elseif ($fillHex !== '') {
            $mode = 'f';
        } elseif ($strokeHex !== '') {
            $mode = 'S';
        }

        $ops[] = sprintf('%.2f %.2f %.2f %.2f re %s', $x, $y, $w, $h, $mode);
        $this->append(implode(' ', $ops));
    }

    public function line(float $x1, float $yTop1, float $x2, float $yTop2, string $strokeHex = '#CBD5E1', float $lineWidth = 0.6): void
    {
        $y1 = $this->pageHeight - $yTop1;
        $y2 = $this->pageHeight - $yTop2;
        [$r, $g, $b] = $this->hexToRgb($strokeHex);
        $this->append(sprintf('%.2f w %.3f %.3f %.3f RG %.2f %.2f m %.2f %.2f l S', $lineWidth, $r, $g, $b, $x1, $y1, $x2, $y2));
    }

    /**
     * Escribe texto en coordenadas desde el borde superior (yTop = baseline aproximada).
     * $font: 'R' (Regular), 'B' (Bold), 'I' (Oblique)
     */
    public function text(float $x, float $yTop, string $text, float $size = 10.0, string $font = 'R', string $colorHex = '#1E293B'): void
    {
        $fontRef = match (strtoupper($font)) {
            'B' => '/F2',
            'I' => '/F3',
            default => '/F1',
        };
        $y = $this->pageHeight - $yTop;
        [$r, $g, $b] = $this->hexToRgb($colorHex);
        $encoded = $this->escapePdfText($text);
        $this->append(sprintf('BT %.3f %.3f %.3f rg %s %.2f Tf %.2f %.2f Td (%s) Tj ET', $r, $g, $b, $fontRef, $size, $x, $y, $encoded));
    }

    /**
     * Dibuja un Código QR real vectorialmente sobre la página PDF
     */
    public function qrCode(string $payload, float $x, float $yTop, float $boxSize = 115.0, string $darkHex = '#002D62'): void
    {
        $matrix = QrGenerator::encode($payload);
        $count = count($matrix);
        $quiet = 2;
        $total = $count + ($quiet * 2);
        $modulePt = $boxSize / $total;

        // Fondo blanco con marco clínico suave
        $this->rect($x, $yTop, $boxSize, $boxSize, '#FFFFFF', '#D4E6F7', 0.8);

        [$r, $g, $b] = $this->hexToRgb($darkHex);
        $ops = [sprintf('%.3f %.3f %.3f rg', $r, $g, $b)];

        for ($row = 0; $row < $count; $row++) {
            for ($col = 0; $col < $count; $col++) {
                if ($matrix[$row][$col]) {
                    $mx = $x + (($col + $quiet) * $modulePt);
                    $myTop = $yTop + (($row + $quiet) * $modulePt);
                    $my = $this->pageHeight - $myTop - $modulePt;
                    $ops[] = sprintf('%.3f %.3f %.3f %.3f re', $mx, $my, $modulePt + 0.08, $modulePt + 0.08);
                }
            }
        }
        $ops[] = 'f';
        $this->append(implode(' ', $ops));
    }

    public function truncateText(string $str, int $maxChars): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $str) ?? $str);
        if (mb_strlen($clean, 'UTF-8') <= $maxChars) {
            return $clean;
        }
        return mb_substr($clean, 0, max(1, $maxChars - 2), 'UTF-8') . '..';
    }

    public function wrapText(string $text, int $maxCharsPerLine): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($clean === '') {
            return ['-'];
        }
        $words = explode(' ', $clean);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = ($current === '') ? $word : ($current . ' ' . $word);
            if (mb_strlen($candidate, 'UTF-8') <= $maxCharsPerLine) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines;
    }

    /**
     * Genera el Comprobante / Ticket Oficial de Cita Médica con Código QR (CU15)
     */
    public static function buildCitaTicket(array $cita, string $verifyUrl): string
    {
        $pdf = new self('P');
        $pdf->addPage();

        // Membrete Institucional Superior
        $pdf->rect(0, 0, 595.28, 96, '#002D62');
        $pdf->rect(0, 96, 595.28, 6, '#2B579A');

        // Icono cruz médica vectorial
        $pdf->rect(38, 26, 38, 38, '#FFFFFF');
        $pdf->rect(53, 33, 8, 24, '#002D62');
        $pdf->rect(45, 41, 24, 8, '#002D62');

        $pdf->text(88, 43, 'CLÍNICA VIDA SALUD', 20, 'B', '#FFFFFF');
        $pdf->text(88, 60, 'SISTEMA WEB DE GESTIÓN DE CITAS MÉDICAS · COMPROBANTE OFICIAL', 9.5, 'R', '#D4E6F7');
        $pdf->text(88, 74, 'Av. San Martín esq. 3er Anillo · Santa Cruz de la Sierra, Bolivia · Tel: (591-3) 345-8800', 8.5, 'R', '#E2E8F0');

        // Caja Código de Cita a la derecha
        $pdf->rect(430, 22, 130, 50, '#2B579A', '#D4E6F7', 0.8);
        $pdf->text(444, 38, 'CÓDIGO DE CITA', 8, 'B', '#D4E6F7');
        $pdf->text(444, 58, (string) $cita['codigo_cita'], 16, 'B', '#FFFFFF');

        // Tarjeta Principal del Comprobante
        $pdf->rect(36, 124, 523, 330, '#FFFFFF', '#CBD5E1', 1.0);
        $pdf->rect(36, 124, 523, 32, '#F4F7FA', '#CBD5E1', 0.6);
        $pdf->text(50, 145, 'DETALLE DE RESERVA Y ATENCIÓN AMBULATORIA', 11, 'B', '#002D62');

        // Badge de Estado
        $estado = (string) ($cita['estado'] ?? 'Programada');
        $badgeColor = match ($estado) {
            'Confirmada'   => '#D97706',
            'Atendida'     => '#198754',
            'Cancelada'    => '#DC3545',
            'Inasistencia' => '#6C757D',
            default        => '#0D6EFD',
        };
        $pdf->rect(445, 130, 100, 20, $badgeColor);
        $pdf->text(460, 144, strtoupper($estado), 8.5, 'B', '#FFFFFF');

        // Columna Izquierda: Datos del Paciente y Cita
        $pdf->text(50, 178, 'PACIENTE TITULAR', 8.5, 'B', '#2B579A');
        $pdf->text(50, 194, $cita['paciente_nombre'] ?? '', 12, 'B', '#0F172A');
        $pdf->text(50, 209, 'Código: ' . ($cita['codigo_paciente'] ?? '') . '   |   CI: ' . ($cita['paciente_ci'] ?? ''), 9.5, 'R', '#475569');
        $pdf->text(50, 223, 'Teléfono: ' . ($cita['paciente_telefono'] ?? '-') . '   |   Grupo Sanguíneo: ' . ($cita['tipo_sangre'] ?? 'Desconocido'), 9, 'R', '#475569');

        $pdf->line(50, 236, 395, 236, '#E2E8F0', 0.8);

        $pdf->text(50, 254, 'ESPECIALIDAD MÉDICA', 8.5, 'B', '#2B579A');
        $pdf->text(50, 270, ($cita['especialidad_nombre'] ?? '') . ' (' . ($cita['codigo_especialidad'] ?? '') . ')', 11, 'B', '#0F172A');

        $pdf->text(50, 294, 'PROFESIONAL MÉDICO ASIGNADO', 8.5, 'B', '#2B579A');
        $pdf->text(50, 310, 'Dr(a). ' . ($cita['medico_nombre'] ?? ''), 11, 'B', '#0F172A');
        $pdf->text(50, 324, 'Matrícula Profesional: ' . ($cita['registro_profesional'] ?? '') . '   |   Cód: ' . ($cita['codigo_medico'] ?? ''), 9, 'R', '#475569');

        // Bloque Fecha y Horario Destacado
        $pdf->rect(50, 340, 345, 52, '#F4F7FA', '#2B579A', 0.8);
        $pdf->text(64, 358, 'FECHA PROGRAMADA', 8, 'B', '#2B579A');
        $pdf->text(64, 377, date('d/m/Y', strtotime((string) $cita['fecha_cita'])), 14, 'B', '#002D62');

        $pdf->text(215, 358, 'BLOQUE HORARIO (30 MIN)', 8, 'B', '#2B579A');
        $horaIni = substr((string) $cita['hora_inicio'], 0, 5);
        $horaFin = substr((string) $cita['hora_fin'], 0, 5);
        $pdf->text(215, 377, "{$horaIni} a {$horaFin} hrs.", 14, 'B', '#002D62');

        // Motivo de consulta
        $pdf->text(50, 412, 'MOTIVO DE CONSULTA:', 8.5, 'B', '#2B579A');
        $motivoLines = $pdf->wrapText((string) ($cita['motivo_consulta'] ?? 'Consulta médica programada'), 62);
        $pdf->text(50, 426, $motivoLines[0] ?? '', 9.5, 'R', '#334155');
        if (isset($motivoLines[1])) {
            $pdf->text(50, 439, $pdf->truncateText($motivoLines[1], 62), 9.5, 'R', '#334155');
        }

        // Columna Derecha: Código QR Real Autogenerado
        $pdf->rect(412, 172, 132, 182, '#F8FAFC', '#D4E6F7', 0.8);
        $pdf->qrCode($verifyUrl, 419, 180, 118, '#002D62');
        $pdf->text(425, 315, 'VALIDACIÓN DIGITAL QR', 8, 'B', '#002D62');
        $pdf->text(422, 328, 'Escanee para verificar', 7.5, 'R', '#475569');
        $pdf->text(422, 339, 'autenticidad en línea', 7.5, 'R', '#475569');

        // Si tiene notas médicas o cancelación, mostrar sección adicional
        if (!empty($cita['notas_medicas'])) {
            $pdf->rect(36, 470, 523, 88, '#F0FDF4', '#198754', 0.8);
            $pdf->text(50, 490, 'RESUMEN DE ATENCIÓN MÉDICA Y EVOLUCIÓN CLÍNICA:', 9, 'B', '#198754');
            $notasLines = $pdf->wrapText((string) $cita['notas_medicas'], 88);
            foreach (array_slice($notasLines, 0, 3) as $idx => $ln) {
                $pdf->text(50, 508 + ($idx * 14), $ln, 9.5, 'R', '#1E293B');
            }
        } elseif (!empty($cita['motivo_cancelacion'])) {
            $pdf->rect(36, 470, 523, 72, '#FEF2F2', '#DC3545', 0.8);
            $pdf->text(50, 490, 'REGISTRO DE CANCELACIÓN DOCUMENTADA:', 9, 'B', '#DC3545');
            $cancLines = $pdf->wrapText((string) $cita['motivo_cancelacion'], 88);
            foreach (array_slice($cancLines, 0, 2) as $idx => $ln) {
                $pdf->text(50, 508 + ($idx * 14), $ln, 9.5, 'R', '#7F1D1D');
            }
        }

        // Indicaciones institucionales para el paciente
        $pdf->rect(36, 578, 523, 108, '#F4F7FA', '#D4E6F7', 0.8);
        $pdf->text(50, 598, 'INDICACIONES IMPORTANTES PARA SU ATENCIÓN CLÍNICA', 9.5, 'B', '#002D62');
        $pdf->text(50, 616, '1. Preséntese en Recepción 15 minutos antes de su hora asignada portando su Cédula de Identidad original.', 8.8, 'R', '#334155');
        $pdf->text(50, 631, '2. En caso de no poder asistir, reprograme o cancele su turno con anticipación para liberar el cupo médico.', 8.8, 'R', '#334155');
        $pdf->text(50, 646, '3. Si cuenta con estudios de laboratorio o imágenes previas relacionadas, llévelos consigo a consultorio.', 8.8, 'R', '#334155');
        $pdf->text(50, 665, 'URL de Verificación: ' . $pdf->truncateText($verifyUrl, 85), 7.8, 'I', '#2B579A');

        // Pie de página oficial
        $pdf->line(36, 780, 559, 780, '#CBD5E1', 0.7);
        $pdf->text(36, 796, 'Emitido el: ' . date('d/m/Y H:i:s') . ' (Hora Oficial Bolivia) · Clínica Vida Salud', 8, 'R', '#64748B');
        $pdf->text(430, 796, 'Documento válido con QR verificable', 8, 'I', '#64748B');

        return $pdf->output();
    }

    /**
     * Genera Reporte Ejecutivo Multipágina Membretado en PDF (CU15)
     * $columns = [['key' => 'codigo', 'label' => 'Código', 'width' => 70], ...]
     */
    public static function buildExecutiveReport(
        string $title,
        string $subtitle,
        array $columns,
        array $rows,
        array $summaryStats = [],
        string $generatedBy = 'Administrador',
        string $orientation = 'L'
    ): string {
        $pdf = new self($orientation);
        $w = $pdf->getWidth();
        $h = $pdf->getHeight();
        $marginX = 32.0;
        $usableW = $w - ($marginX * 2);

        // Normalizar anchos proporcionales al ancho útil de la hoja
        $rawTotal = array_sum(array_column($columns, 'width')) ?: 100;
        foreach ($columns as &$col) {
            $col['ptWidth'] = ($col['width'] / $rawTotal) * $usableW;
        }
        unset($col);

        $rowsPerPageFirst = !empty($summaryStats) ? ($orientation === 'L' ? 16 : 24) : ($orientation === 'L' ? 19 : 28);
        $rowsPerPageNext  = ($orientation === 'L') ? 21 : 31;

        $chunks = [];
        if (empty($rows)) {
            $chunks[] = [];
        } else {
            $chunks[] = array_slice($rows, 0, $rowsPerPageFirst);
            $remaining = array_slice($rows, $rowsPerPageFirst);
            while (!empty($remaining)) {
                $chunks[] = array_splice($remaining, 0, $rowsPerPageNext);
            }
        }

        $totalPages = count($chunks);

        foreach ($chunks as $pageIdx => $pageRows) {
            $pdf->addPage();

            // Encabezado Institucional
            $pdf->rect(0, 0, $w, 68, '#002D62');
            $pdf->rect(0, 68, $w, 4, '#2B579A');

            $pdf->rect($marginX, 16, 30, 30, '#FFFFFF');
            $pdf->rect($marginX + 12, 21, 6, 20, '#002D62');
            $pdf->rect($marginX + 7, 28, 16, 6, '#002D62');

            $pdf->text($marginX + 40, 31, 'CLÍNICA VIDA SALUD · REPORTE EJECUTIVO DE GESTIÓN', 13, 'B', '#FFFFFF');
            $pdf->text($marginX + 40, 47, $pdf->truncateText($title, 95), 10.5, 'B', '#D4E6F7');
            $pdf->text($marginX + 40, 60, $pdf->truncateText($subtitle, 115), 8.2, 'R', '#E2E8F0');

            $cursorY = 86.0;

            // Tarjetas KPI en la primera página
            if ($pageIdx === 0 && !empty($summaryStats)) {
                $countStats = count($summaryStats);
                $gap = 10.0;
                $cardW = ($usableW - ($gap * ($countStats - 1))) / $countStats;
                $i = 0;
                foreach ($summaryStats as $label => $val) {
                    $cx = $marginX + ($i * ($cardW + $gap));
                    $pdf->rect($cx, $cursorY, $cardW, 40, '#F4F7FA', '#D4E6F7', 0.8);
                    $pdf->text($cx + 10, $cursorY + 15, strtoupper($pdf->truncateText((string) $label, 28)), 7.5, 'B', '#2B579A');
                    $pdf->text($cx + 10, $cursorY + 32, (string) $val, 12, 'B', '#002D62');
                    $i++;
                }
                $cursorY += 52.0;
            }

            // Cabecera de Tabla
            $pdf->rect($marginX, $cursorY, $usableW, 22, '#2B579A');
            $colX = $marginX;
            foreach ($columns as $col) {
                $pdf->text($colX + 5, $cursorY + 14.5, strtoupper((string) $col['label']), 7.8, 'B', '#FFFFFF');
                $colX += $col['ptWidth'];
            }
            $cursorY += 22.0;

            // Filas de Tabla
            if (empty($pageRows)) {
                $pdf->rect($marginX, $cursorY, $usableW, 28, '#F8FAFC', '#E2E8F0', 0.5);
                $pdf->text($marginX + 12, $cursorY + 18, 'No se encontraron registros para los criterios de filtrado seleccionados.', 9, 'I', '#64748B');
            } else {
                foreach ($pageRows as $rIdx => $row) {
                    $bg = ($rIdx % 2 === 0) ? '#FFFFFF' : '#F8FAFC';
                    $pdf->rect($marginX, $cursorY, $usableW, 19, $bg, '#E2E8F0', 0.4);
                    $colX = $marginX;
                    foreach ($columns as $col) {
                        $val = (string) ($row[$col['key']] ?? '-');
                        $maxChars = max(5, (int) floor($col['ptWidth'] / 4.7));
                        $pdf->text($colX + 5, $cursorY + 13, $pdf->truncateText($val, $maxChars), 8.0, 'R', '#1E293B');
                        $colX += $col['ptWidth'];
                    }
                    $cursorY += 19.0;
                }
            }

            // Pie de página
            $footerY = $h - 24.0;
            $pdf->line($marginX, $footerY - 10, $w - $marginX, $footerY - 10, '#CBD5E1', 0.6);
            $pdf->text($marginX, $footerY, 'Generado por: ' . $generatedBy . '  |  Fecha: ' . date('d/m/Y H:i:s') . '  |  Total registros: ' . count($rows), 7.8, 'R', '#64748B');
            $pageNumText = sprintf('Página %d de %d', $pageIdx + 1, $totalPages);
            $pdf->text($w - $marginX - 70, $footerY, $pageNumText, 8.0, 'B', '#002D62');
        }

        return $pdf->output();
    }

    public function output(): string
    {
        if (empty($this->pages)) {
            $this->addPage();
        }

        $objects = [];
        // 1: Catalog, 2: Pages, 3: Font Regular, 4: Font Bold, 5: Font Oblique
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>";

        $kids = [];
        $nextId = 6;
        foreach ($this->pages as $streamContent) {
            $pageObjId = $nextId++;
            $contentObjId = $nextId++;
            $kids[] = "{$pageObjId} 0 R";

            $len = strlen($streamContent);
            $objects[$contentObjId] = "<< /Length {$len} >>\nstream\n{$streamContent}\nendstream";
            $objects[$pageObjId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents %d 0 R >>",
                $this->pageWidth,
                $this->pageHeight,
                $contentObjId
            );
        }

        $objects[2] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $kids), count($kids));
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $totalObjs = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjs}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        return $pdf;
    }

    private function append(string $cmd): void
    {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
        $this->pages[$this->currentPage] .= $cmd . "\n";
    }

    private function hexToRgb(string $hex): array
    {
        $clean = ltrim($hex, '#');
        if (strlen($clean) === 3) {
            $clean = $clean[0] . $clean[0] . $clean[1] . $clean[1] . $clean[2] . $clean[2];
        }
        $int = hexdec($clean);
        return [
            (($int >> 16) & 255) / 255.0,
            (($int >> 8) & 255) / 255.0,
            ($int & 255) / 255.0,
        ];
    }

    private function escapePdfText(string $utf8): string
    {
        $win = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $utf8);
        if ($win === false) {
            $win = $utf8;
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $win);
    }
}
