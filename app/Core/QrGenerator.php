<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generador Nativo QR Code ISO/IEC 18004 en PHP Puro (Modo Byte, Nivel L, Versiones 1-10)
 * Cero dependencias externas, cero llamadas de red y cero requerimiento de extensión GD.
 * Produce matrices booleanas exactas para renderizado vectorial en PDF y SVG en vistas web.
 */
final class QrGenerator
{
    private const VERSIONS_L = [
        1  => ['total_data' => 19,  'ec_per_block' => 7,  'blocks' => [[1, 19]],           'align' => []],
        2  => ['total_data' => 34,  'ec_per_block' => 10, 'blocks' => [[1, 34]],           'align' => [6, 18]],
        3  => ['total_data' => 55,  'ec_per_block' => 15, 'blocks' => [[1, 55]],           'align' => [6, 22]],
        4  => ['total_data' => 80,  'ec_per_block' => 20, 'blocks' => [[1, 80]],           'align' => [6, 26]],
        5  => ['total_data' => 108, 'ec_per_block' => 26, 'blocks' => [[1, 108]],          'align' => [6, 30]],
        6  => ['total_data' => 136, 'ec_per_block' => 18, 'blocks' => [[2, 68]],           'align' => [6, 34]],
        7  => ['total_data' => 156, 'ec_per_block' => 20, 'blocks' => [[2, 78]],           'align' => [6, 22, 38]],
        8  => ['total_data' => 194, 'ec_per_block' => 24, 'blocks' => [[2, 97]],           'align' => [6, 24, 42]],
        9  => ['total_data' => 232, 'ec_per_block' => 30, 'blocks' => [[2, 116]],          'align' => [6, 26, 46]],
        10 => ['total_data' => 274, 'ec_per_block' => 18, 'blocks' => [[2, 68], [2, 69]], 'align' => [6, 28, 50]],
    ];

    private static array $gfExp = [];
    private static array $gfLog = [];
    private static bool $gfInitialized = false;

    public static function toSvg(string $text, int $pixelSize = 160, int $margin = 2, string $darkColor = '#002D62'): string
    {
        $matrix = self::encode($text);
        $count = count($matrix);
        $totalModules = $count + ($margin * 2);

        $paths = [];
        for ($r = 0; $r < $count; $r++) {
            for ($c = 0; $c < $count; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $paths[] = "M{$x},{$y}h1v1h-1z";
                }
            }
        }
        $d = implode('', $paths);
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges"><rect width="100%%" height="100%%" fill="#FFFFFF"/><path d="%s" fill="%s"/></svg>',
            $pixelSize,
            $pixelSize,
            $totalModules,
            $totalModules,
            $d,
            htmlspecialchars($darkColor, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Devuelve matriz 2D de booleanos [fila][columna] (true = módulo oscuro)
     */
    public static function encode(string $data): array
    {
        self::initGaloisField();

        $bytes = array_values(unpack('C*', $data) ?: []);
        $len = count($bytes);

        $version = self::pickVersion($len);
        $spec = self::VERSIONS_L[$version];
        $size = 17 + ($version * 4);

        // 1. Construir flujo de bits (Modo Byte = 0100)
        $bits = [];
        self::appendBits($bits, 0b0100, 4);
        $countBits = ($version <= 9) ? 8 : 16;
        self::appendBits($bits, $len, $countBits);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }

        $maxBits = $spec['total_data'] * 8;
        $terminator = min(4, $maxBits - count($bits));
        self::appendBits($bits, 0, $terminator);

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $dataCodewords = [];
        for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
            $val = 0;
            for ($j = 0; $j < 8; $j++) {
                $val = ($val << 1) | $bits[$i + $j];
            }
            $dataCodewords[] = $val;
        }

        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($dataCodewords) < $spec['total_data']) {
            $dataCodewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        // 2. Dividir en bloques y calcular corrección Reed-Solomon
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        $genPoly = self::rsGeneratorPoly($spec['ec_per_block']);

        foreach ($spec['blocks'] as [$numBlocks, $blockDataLen]) {
            for ($b = 0; $b < $numBlocks; $b++) {
                $chunk = array_slice($dataCodewords, $offset, $blockDataLen);
                $offset += $blockDataLen;
                $dataBlocks[] = $chunk;
                $ecBlocks[] = self::rsComputeRemainder($chunk, $genPoly);
            }
        }

        // Intercalar codewords
        $finalCodewords = [];
        $maxDataLen = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $blk) {
                if (isset($blk[$i])) {
                    $finalCodewords[] = $blk[$i];
                }
            }
        }
        for ($i = 0; $i < $spec['ec_per_block']; $i++) {
            foreach ($ecBlocks as $eblk) {
                $finalCodewords[] = $eblk[$i];
            }
        }

        $finalBits = [];
        foreach ($finalCodewords as $cw) {
            self::appendBits($finalBits, $cw, 8);
        }

        // 3. Crear matriz base y patrones funcionales
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $isFunc = array_fill(0, $size, array_fill(0, $size, false));

        self::drawFinderPattern($modules, $isFunc, 0, 0, $size);
        self::drawFinderPattern($modules, $isFunc, $size - 7, 0, $size);
        self::drawFinderPattern($modules, $isFunc, 0, $size - 7, $size);

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = ($i % 2 === 0);
            $modules[6][$i] = $dark;
            $isFunc[6][$i] = true;
            $modules[$i][6] = $dark;
            $isFunc[$i][6] = true;
        }

        // Alignment patterns
        $align = $spec['align'];
        $aCount = count($align);
        for ($i = 0; $i < $aCount; $i++) {
            for ($j = 0; $j < $aCount; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $aCount - 1) || ($i === $aCount - 1 && $j === 0)) {
                    continue;
                }
                self::drawAlignmentPattern($modules, $isFunc, $align[$i], $align[$j]);
            }
        }

        // Reservar áreas de formato y módulo oscuro fijo
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $isFunc[8][$i] = true;
                $isFunc[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $isFunc[8][$size - 1 - $i] = true;
            $isFunc[$size - 1 - $i][8] = true;
        }
        $modules[$size - 8][8] = true;
        $isFunc[$size - 8][8] = true;

        if ($version >= 7) {
            self::drawVersionInfo($modules, $isFunc, $version, $size);
        }

        // 4. Colocar bits en zigzag derecha a izquierda
        $bitIdx = 0;
        $totalBits = count($finalBits);
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = ((($right + 1) & 2) === 0);
                    $y = $upward ? ($size - 1 - $vert) : $vert;
                    if (!$isFunc[$y][$x]) {
                        $dark = ($bitIdx < $totalBits) ? ($finalBits[$bitIdx] === 1) : false;
                        $modules[$y][$x] = $dark;
                        $bitIdx++;
                    }
                }
            }
        }

        // 5. Seleccionar máscara óptima con menor penalización
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = $modules;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $modules;
            self::applyMask($candidate, $isFunc, $mask, $size);
            self::drawFormatBits($candidate, $mask, $size);
            $penalty = self::computePenalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $candidate;
            }
        }

        return $bestMatrix;
    }

    private static function pickVersion(int $byteLength): int
    {
        foreach (self::VERSIONS_L as $ver => $spec) {
            $headerBits = 4 + (($ver <= 9) ? 8 : 16);
            $capacityBytes = (int) floor((($spec['total_data'] * 8) - $headerBits) / 8);
            if ($byteLength <= $capacityBytes) {
                return $ver;
            }
        }
        throw new \InvalidArgumentException('El contenido para el código QR supera la capacidad máxima soportada (271 bytes).');
    }

    private static function appendBits(array &$bits, int $val, int $len): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($val >> $i) & 1;
        }
    }

    private static function initGaloisField(): void
    {
        if (self::$gfInitialized) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gfExp[$i] = $x;
            self::$gfLog[$x] = $i;
            $x <<= 1;
            if (($x & 0x100) !== 0) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gfExp[$i] = self::$gfExp[$i - 255];
        }
        self::$gfInitialized = true;
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$gfExp[self::$gfLog[$a] + self::$gfLog[$b]];
    }

    private static function rsGeneratorPoly(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            $root = self::$gfExp[$i];
            foreach ($poly as $idx => $coef) {
                $next[$idx] ^= $coef;
                $next[$idx + 1] ^= self::gfMul($coef, $root);
            }
            $poly = $next;
        }
        // Quitar término líder 1 para división sintética
        array_shift($poly);
        return $poly;
    }

    private static function rsComputeRemainder(array $data, array $divisor): array
    {
        $deg = count($divisor);
        $result = array_fill(0, $deg, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            if ($factor !== 0) {
                for ($i = 0; $i < $deg; $i++) {
                    $result[$i] ^= self::gfMul($divisor[$i], $factor);
                }
            }
        }
        return $result;
    }

    private static function drawFinderPattern(array &$modules, array &$isFunc, int $left, int $top, int $size): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $x = $left + $dx;
                $y = $top + $dy;
                if ($x < 0 || $x >= $size || $y < 0 || $y >= $size) {
                    continue;
                }
                $inOuter = ($dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6);
                $inWhite = ($dx >= 1 && $dx <= 5 && $dy >= 1 && $dy <= 5);
                $inCore  = ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4);
                $modules[$y][$x] = $inOuter && (!$inWhite || $inCore);
                $isFunc[$y][$x] = true;
            }
        }
    }

    private static function drawAlignmentPattern(array &$modules, array &$isFunc, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $modules[$cy + $dy][$cx + $dx] = ($dist !== 1);
                $isFunc[$cy + $dy][$cx + $dx] = true;
            }
        }
    }

    private static function drawVersionInfo(array &$modules, array &$isFunc, int $version, int $size): void
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = ((($bits >> $i) & 1) === 1);
            $a = $size - 11 + ($i % 3);
            $b = intdiv($i, 3);
            $modules[$b][$a] = $dark;
            $isFunc[$b][$a] = true;
            $modules[$a][$b] = $dark;
            $isFunc[$a][$b] = true;
        }
    }

    private static function applyMask(array &$modules, array $isFunc, int $mask, int $size): void
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($isFunc[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => (($x + $y) % 2 === 0),
                    1 => ($y % 2 === 0),
                    2 => ($x % 3 === 0),
                    3 => (($x + $y) % 3 === 0),
                    4 => (((intdiv($y, 2) + intdiv($x, 3)) % 2) === 0),
                    5 => ((($x * $y) % 2 + ($x * $y) % 3) === 0),
                    6 => (((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0),
                    7 => (((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0),
                    default => false,
                };
                if ($invert) {
                    $modules[$y][$x] = !$modules[$y][$x];
                }
            }
        }
    }

    private static function drawFormatBits(array &$modules, int $mask, int $size): void
    {
        // Nivel L = 0b01
        $data = (0b01 << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        // Primera copia alrededor de esquina superior izquierda
        for ($i = 0; $i <= 5; $i++) {
            $modules[8][$i] = ((($bits >> $i) & 1) === 1);
        }
        $modules[8][7] = ((($bits >> 6) & 1) === 1);
        $modules[8][8] = ((($bits >> 7) & 1) === 1);
        $modules[7][8] = ((($bits >> 8) & 1) === 1);
        for ($i = 9; $i < 15; $i++) {
            $modules[14 - $i][8] = ((($bits >> $i) & 1) === 1);
        }

        // Segunda copia en esquinas inferior izquierda y superior derecha
        for ($i = 0; $i < 8; $i++) {
            $modules[8][$size - 1 - $i] = ((($bits >> $i) & 1) === 1);
        }
        for ($i = 8; $i < 15; $i++) {
            $modules[$size - 15 + $i][8] = ((($bits >> $i) & 1) === 1);
        }
    }

    private static function computePenalty(array $m, int $size): int
    {
        $penalty = 0;
        // Regla 1: 5+ módulos consecutivos del mismo color en filas y columnas
        for ($r = 0; $r < $size; $r++) {
            $runColor = $m[$r][0];
            $runLen = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $penalty += 3;
                    } elseif ($runLen > 5) {
                        $penalty++;
                    }
                } else {
                    $runColor = $m[$r][$c];
                    $runLen = 1;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            $runColor = $m[0][$c];
            $runLen = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($m[$r][$c] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $penalty += 3;
                    } elseif ($runLen > 5) {
                        $penalty++;
                    }
                } else {
                    $runColor = $m[$r][$c];
                    $runLen = 1;
                }
            }
        }
        // Regla 2: Bloques 2x2 homogéneos
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $val = $m[$r][$c];
                if ($val === $m[$r][$c + 1] && $val === $m[$r + 1][$c] && $val === $m[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }
        return $penalty;
    }
}
