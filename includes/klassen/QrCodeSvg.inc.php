<?php
declare(strict_types=1);

/**
 * EroCloud Local ISO/IEC 18004 QR-Code SVG Generator (100% Offline & DSGVO-konform)
 * Compatible with PHP 8.0.30 and PHP 8.4.27
 *
 * Erzeugt standardkonforme, von jeder Smartphone-Kamera (iOS & Android) sofort scannbare
 * QR-Codes im Byte-Mode (Error Correction Level L, Version 1 bis 10, bis zu 271 Bytes).
 */

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

class QrCodeSvg
{
    /**
     * Spezifikationstabelle für QR-Versionen 1..10 bei Error Correction Level L (Byte-Mode):
     * [total_data_codewords, ec_codewords_per_block, num_blocks_g1, data_cw_g1, num_blocks_g2, data_cw_g2, alignment_centers]
     */
    private const VERSION_TABLE_L = [
        1  => [19,  7,  1, 19,  0, 0,  []],
        2  => [34,  10, 1, 34,  0, 0,  [6, 18]],
        3  => [55,  15, 1, 55,  0, 0,  [6, 22]],
        4  => [80,  20, 1, 80,  0, 0,  [6, 26]],
        5  => [108, 26, 1, 108, 0, 0,  [6, 30]],
        6  => [136, 18, 2, 68,  0, 0,  [6, 34]],
        7  => [156, 20, 2, 78,  0, 0,  [6, 22, 38]],
        8  => [194, 24, 2, 97,  0, 0,  [6, 24, 42]],
        9  => [232, 30, 2, 116, 0, 0,  [6, 26, 46]],
        10 => [274, 18, 2, 68,  2, 69, [6, 28, 50]],
    ];

    /**
     * Vorausberechnete 15-Bit Format-Informationen für Error Correction Level L (01) und Masken 0..7
     */
    private const FORMAT_BITS_L = [
        0 => 0x77C4,
        1 => 0x72F3,
        2 => 0x7DAA,
        3 => 0x789D,
        4 => 0x662F,
        5 => 0x6318,
        6 => 0x6C41,
        7 => 0x6976,
    ];

    /**
     * Vorausberechnete 18-Bit Versions-Informationen für Version 7..10
     */
    private const VERSION_BITS = [
        7  => 0x07C94,
        8  => 0x085BC,
        9  => 0x09A99,
        10 => 0x0A4D3,
    ];

    /**
     * Generiert ein skalierbares Inline-SVG für den übergebenen Text/Link.
     */
    public static function render(string $data, int $pixelSize = 240, int $margin = 4): string
    {
        $bytes = array_values(unpack('C*', $data) ?: []);
        $len   = count($bytes);

        // Passende QR-Version (1..10) wählen
        $version = 10;
        foreach (self::VERSION_TABLE_L as $v => $spec) {
            $maxDataCw = (int)$spec[0];
            $headerBytes = ($v <= 9) ? 2 : 3; // 4 Bit Mode + 8/16 Bit Length
            if ($len + $headerBytes <= $maxDataCw) {
                $version = $v;
                break;
            }
        }

        $spec      = self::VERSION_TABLE_L[$version];
        $totalData = (int)$spec[0];
        $ecPerBlk  = (int)$spec[1];
        $blocksG1  = (int)$spec[2];
        $cwG1      = (int)$spec[3];
        $blocksG2  = (int)$spec[4];
        $cwG2      = (int)$spec[5];
        $alignPos  = (array)$spec[6];

        // 1. Bitstrom für Byte-Mode (0100) aufbauen
        $bits = [];
        self::appendBits($bits, 0b0100, 4);
        $lenBits = ($version <= 9) ? 8 : 16;
        self::appendBits($bits, $len, $lenBits);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }

        // Terminator (bis zu 4 Null-Bits)
        $maxBits = $totalData * 8;
        $termLen = min(4, $maxBits - count($bits));
        if ($termLen > 0) {
            self::appendBits($bits, 0, $termLen);
        }

        // Auf Byte-Grenze auffüllen
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // In Codewords (Bytes) umwandeln und mit Pad-Bytes (0xEC, 0x11) füllen
        $dataCodewords = [];
        for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
            $val = 0;
            for ($j = 0; $j < 8; $j++) {
                $val = ($val << 1) | $bits[$i + $j];
            }
            $dataCodewords[] = $val;
        }

        $padToggle = true;
        while (count($dataCodewords) < $totalData) {
            $dataCodewords[] = $padToggle ? 0xEC : 0x11;
            $padToggle       = !$padToggle;
        }

        // 2. Reed-Solomon Error Correction pro Block berechnen und interleaven
        $dataBlocks = [];
        $ecBlocks   = [];
        $offset     = 0;

        for ($b = 0; $b < $blocksG1; $b++) {
            $blk          = array_slice($dataCodewords, $offset, $cwG1);
            $offset      += $cwG1;
            $dataBlocks[] = $blk;
            $ecBlocks[]   = self::computeReedSolomon($blk, $ecPerBlk);
        }
        for ($b = 0; $b < $blocksG2; $b++) {
            $blk          = array_slice($dataCodewords, $offset, $cwG2);
            $offset      += $cwG2;
            $dataBlocks[] = $blk;
            $ecBlocks[]   = self::computeReedSolomon($blk, $ecPerBlk);
        }

        $finalCodewords = [];
        $maxCwPerBlock  = max($cwG1, $cwG2);
        for ($i = 0; $i < $maxCwPerBlock; $i++) {
            foreach ($dataBlocks as $blk) {
                if (isset($blk[$i])) {
                    $finalCodewords[] = $blk[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlk; $i++) {
            foreach ($ecBlocks as $eblk) {
                if (isset($eblk[$i])) {
                    $finalCodewords[] = $eblk[$i];
                }
            }
        }

        $finalBits = [];
        foreach ($finalCodewords as $cw) {
            self::appendBits($finalBits, $cw, 8);
        }

        // 3. Matrix initialisieren und Funktionsmuster platzieren
        $size       = $version * 4 + 17;
        $modules    = array_fill(0, $size, array_fill(0, $size, false));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        // Finder-Patterns (Oben-Links, Oben-Rechts, Unten-Links)
        self::drawFinderPattern($modules, $isFunction, 3, 3, $size);
        self::drawFinderPattern($modules, $isFunction, $size - 4, 3, $size);
        self::drawFinderPattern($modules, $isFunction, 3, $size - 4, $size);

        // Timing-Patterns
        for ($i = 0; $i < $size; $i++) {
            if (!$isFunction[6][$i]) {
                $isFunction[6][$i] = true;
                $modules[6][$i]    = ($i % 2 === 0);
            }
            if (!$isFunction[$i][6]) {
                $isFunction[$i][6] = true;
                $modules[$i][6]    = ($i % 2 === 0);
            }
        }

        // Alignment-Patterns
        $numAlign = count($alignPos);
        for ($i = 0; $i < $numAlign; $i++) {
            for ($j = 0; $j < $numAlign; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $numAlign - 1) || ($i === $numAlign - 1 && $j === 0)) {
                    continue;
                }
                self::drawAlignmentPattern($modules, $isFunction, (int)$alignPos[$i], (int)$alignPos[$j]);
            }
        }

        // Format-Info-Felder und Dark-Module reservieren
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $isFunction[8][$i] = true;
                $isFunction[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $isFunction[8][$size - 1 - $i] = true;
            $isFunction[$size - 1 - $i][8] = true;
        }
        $modules[$size - 8][8]    = true; // Dark Module
        $isFunction[$size - 8][8] = true;

        // Version-Info (für Version >= 7)
        if ($version >= 7 && isset(self::VERSION_BITS[$version])) {
            $vBits = self::VERSION_BITS[$version];
            for ($i = 0; $i < 18; $i++) {
                $bit = (($vBits >> $i) & 1) === 1;
                $a   = intdiv($i, 3);
                $b   = ($i % 3) + $size - 11;
                $modules[$b][$a]    = $bit;
                $isFunction[$b][$a] = true;
                $modules[$a][$b]    = $bit;
                $isFunction[$a][$b] = true;
            }
        }

        // 4. Daten-Bits im Zickzack von rechts unten eintragen
        $bitIdx = 0;
        $bitLen = count($finalBits);
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x      = $right - $j;
                    $upward = ((($right + 1) & 2) === 0);
                    $y      = $upward ? ($size - 1 - $vert) : $vert;
                    if (!$isFunction[$y][$x] && $bitIdx < $bitLen) {
                        $modules[$y][$x] = ($finalBits[$bitIdx] === 1);
                        $bitIdx++;
                    }
                }
            }
        }

        // 5. Beste Maske (0..7) mit geringster Penalty auswählen und anwenden
        $bestMask    = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix  = $modules;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $modules;
            self::applyMask($candidate, $isFunction, $size, $mask);
            self::drawFormatBits($candidate, $size, $mask);
            $penalty = self::calculatePenalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask    = $mask;
                $bestMatrix  = $candidate;
            }
        }

        // 6. Kompaktes SVG-Path-Rendering
        $totalModules = $size + ($margin * 2);
        $pathParts    = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($bestMatrix[$y][$x]) {
                    $px          = $x + $margin;
                    $py          = $y + $margin;
                    $pathParts[] = "M{$px},{$py}h1v1h-1z";
                }
            }
        }

        $pathD = implode('', $pathParts);
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalModules . ' ' . $totalModules . '" width="' . $pixelSize . '" height="' . $pixelSize . '" shape-rendering="crispEdges" role="img" aria-label="QR-Code">'
            . '<rect width="100%" height="100%" fill="#FFFFFF"/>'
            . '<path d="' . $pathD . '" fill="#0F172A"/>'
            . '</svg>';
    }

    private static function appendBits(array &$bits, int $val, int $len): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($val >> $i) & 1;
        }
    }

    private static function drawFinderPattern(array &$modules, array &$isFunction, int $cx, int $cy, int $size): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                    $dist               = max(abs($dx), abs($dy));
                    $modules[$y][$x]    = ($dist !== 2 && $dist !== 4);
                    $isFunction[$y][$x] = true;
                }
            }
        }
    }

    private static function drawAlignmentPattern(array &$modules, array &$isFunction, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist                       = max(abs($dx), abs($dy));
                $modules[$cy + $dy][$cx + $dx]    = ($dist !== 1);
                $isFunction[$cy + $dy][$cx + $dx] = true;
            }
        }
    }

    private static function applyMask(array &$modules, array $isFunction, int $size, int $mask): void
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($isFunction[$y][$x]) {
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
                    default => (((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0),
                };
                if ($invert) {
                    $modules[$y][$x] = !$modules[$y][$x];
                }
            }
        }
    }

    private static function drawFormatBits(array &$modules, int $size, int $mask): void
    {
        $bits = self::FORMAT_BITS_L[$mask] ?? self::FORMAT_BITS_L[0];

        // Erste Kopie um oberen linken Finder
        for ($i = 0; $i <= 5; $i++) {
            $modules[8][$i] = (($bits >> $i) & 1) === 1;
        }
        $modules[8][7] = (($bits >> 6) & 1) === 1;
        $modules[8][8] = (($bits >> 7) & 1) === 1;
        $modules[7][8] = (($bits >> 8) & 1) === 1;
        for ($i = 9; $i < 15; $i++) {
            $modules[14 - $i][8] = (($bits >> $i) & 1) === 1;
        }

        // Zweite Kopie
        for ($i = 0; $i < 8; $i++) {
            $modules[8][$size - 1 - $i] = (($bits >> $i) & 1) === 1;
        }
        for ($i = 8; $i < 15; $i++) {
            $modules[$size - 15 + $i][8] = (($bits >> $i) & 1) === 1;
        }
    }

    private static function calculatePenalty(array $modules, int $size): int
    {
        $penalty = 0;
        for ($y = 0; $y < $size; $y++) {
            $runColor = $modules[$y][0];
            $runLen   = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($modules[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $penalty += 3;
                    } elseif ($runLen > 5) {
                        $penalty++;
                    }
                } else {
                    $runColor = $modules[$y][$x];
                    $runLen   = 1;
                }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            $runColor = $modules[0][$x];
            $runLen   = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($modules[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $penalty += 3;
                    } elseif ($runLen > 5) {
                        $penalty++;
                    }
                } else {
                    $runColor = $modules[$y][$x];
                    $runLen   = 1;
                }
            }
        }
        return $penalty;
    }

    /**
     * Berechnet die Reed-Solomon Error-Correction-Codewords in GF(2^8) mit Primitivpolynom 0x11D.
     *
     * @param array<int, int> $data
     * @return array<int, int>
     */
    private static function computeReedSolomon(array $data, int $degree): array
    {
        $gen = [1];
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            $nextGen = array_fill(0, count($gen) + 1, 0);
            for ($j = 0, $n = count($gen); $j < $n; $j++) {
                $nextGen[$j]     ^= $gen[$j];
                $nextGen[$j + 1] ^= self::gfMultiply($gen[$j], $root);
            }
            $gen  = $nextGen;
            $root = self::gfMultiply($root, 0x02);
        }

        // Generator-Polynom ohne führende 1
        array_shift($gen);
        $result = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ (int)array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::gfMultiply($gen[$i], $factor);
            }
        }

        return $result;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }
}
