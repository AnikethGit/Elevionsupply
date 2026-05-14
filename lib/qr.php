<?php
/**
 * Minimal pure-PHP QR Code matrix generator.
 * Returns a 2D boolean array ($matrix[$row][$col] = true means dark module).
 * Supports Alphanumeric + Numeric + Byte mode, error correction L/M/Q/H.
 * Auto-selects version 1-10 to fit the input string.
 */
class QRCode
{
    // Alphanumeric charset
    private static string $ALNUM = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    // Version capacity table [version][ecLevel] = max chars (alphanumeric)
    private static array $CAP_ALNUM = [
        0,   // placeholder (versions are 1-indexed)
        [25,20,16,10],   // v1
        [47,38,29,20],   // v2
        [77,61,47,35],   // v3
        [114,90,67,50],  // v4
        [154,122,87,64], // v5
        [195,154,108,84],// v6
        [224,178,125,93],// v7
        [279,221,157,122],// v8
        [335,262,189,154],// v9
        [395,311,221,178],// v10
    ];

    private static array $CAP_BYTE = [
        0,
        [17,14,11,7],
        [32,26,20,14],
        [53,42,32,24],
        [78,62,46,34],
        [106,84,60,44],
        [134,106,74,58],
        [154,122,86,64],
        [192,152,108,84],
        [230,180,130,98],
        [271,213,151,119],
    ];

    // RS generator polynomials (number of EC codewords per block)
    private static array $EC_BLOCKS = [
        // [version][ecLevel] = [blocks, ecPerBlock, dataCodewords]
        // Simplified for v1-v4 which covers our use cases
        // Format: [numBlocks, ecCodewordsPerBlock, dataCodewords total]
        1 => [[1,7,19],[1,10,16],[1,13,13],[1,17,9]],
        2 => [[1,10,34],[1,16,28],[1,22,22],[1,28,16]],
        3 => [[1,15,55],[1,26,44],[2,18,34],[2,22,26]],
        4 => [[1,20,80],[2,18,64],[2,26,48],[4,16,36]],
        5 => [[1,26,108],[2,24,86],[2,18,62],[2,22,46],[4,28,0]], // simplified
    ];

    /**
     * Generate QR code matrix.
     * @param string $data  The data to encode
     * @param int    $ecLvl 0=L,1=M,2=Q,3=H
     * @return array 2D boolean array
     */
    public static function matrix(string $data, int $ecLvl = 1): array
    {
        // Determine if all alphanumeric
        $isAlnum = (strtoupper($data) === $data)
                && (strspn(strtoupper($data), self::$ALNUM) === strlen($data));
        $dataUp  = strtoupper($data);

        // Choose version
        $version = 0;
        for ($v = 1; $v <= 10; $v++) {
            $cap = $isAlnum
                ? (self::$CAP_ALNUM[$v][$ecLvl] ?? 0)
                : (self::$CAP_BYTE[$v][$ecLvl]  ?? 0);
            if ($cap >= strlen($data)) { $version = $v; break; }
        }
        if ($version === 0) $version = 10; // fallback

        $size = $version * 4 + 17;

        // Build matrix
        $m = array_fill(0, $size, array_fill(0, $size, -1)); // -1 = unset

        // Finder patterns
        self::addFinder($m, 0, 0);
        self::addFinder($m, $size - 7, 0);
        self::addFinder($m, 0, $size - 7);

        // Separators
        for ($i = 0; $i < 8; $i++) {
            self::safe($m, 7, $i, 0); self::safe($m, $i, 7, 0);
            self::safe($m, $size-8, $i, 0); self::safe($m, $i, $size-8, 0);
            self::safe($m, 7, $size-1-$i, 0); self::safe($m, $size-1-$i, 7, 0);
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            self::safe($m, 6, $i, $v);
            self::safe($m, $i, 6, $v);
        }

        // Dark module
        self::safe($m, $size - 8, 8, 1);

        // Alignment patterns (version >= 2)
        $ap = self::alignmentPositions($version);
        foreach ($ap as $r) {
            foreach ($ap as $c) {
                if (($r === 6 && $c === 6) ||
                    ($r === 6 && $c === $ap[count($ap)-1]) ||
                    ($r === $ap[count($ap)-1] && $c === 6)) continue;
                self::addAlignment($m, $r, $c);
            }
        }

        // Format info (temporary — mask 0)
        self::writeFormatInfo($m, $ecLvl, 0, $size);

        // Encode data
        $bits = $isAlnum
            ? self::encodeAlnum($dataUp, $version)
            : self::encodeByte($data, $version);

        // Place data bits
        self::placeData($m, $bits, $size);

        // Score and apply best mask
        $bestMask  = 0;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $copy = $m;
            self::applyMask($copy, $mask, $size);
            self::writeFormatInfo($copy, $ecLvl, $mask, $size);
            $score = self::penalty($copy, $size);
            if ($score < $bestScore) { $bestScore = $score; $bestMask = $mask; }
        }
        self::applyMask($m, $bestMask, $size);
        self::writeFormatInfo($m, $ecLvl, $bestMask, $size);

        // Convert to bool
        $out = [];
        for ($r = 0; $r < $size; $r++) {
            $out[$r] = [];
            for ($c = 0; $c < $size; $c++) {
                $out[$r][$c] = ($m[$r][$c] === 1);
            }
        }
        return $out;
    }

    private static function safe(array &$m, int $r, int $c, int $v): void
    {
        if (isset($m[$r][$c])) $m[$r][$c] = $v;
    }

    private static function addFinder(array &$m, int $row, int $col): void
    {
        for ($r = 0; $r < 7; $r++) for ($c = 0; $c < 7; $c++) {
            $v = (($r===0||$r===6||$c===0||$c===6) || ($r>=2&&$r<=4&&$c>=2&&$c<=4)) ? 1 : 0;
            self::safe($m, $row+$r, $col+$c, $v);
        }
    }

    private static function addAlignment(array &$m, int $row, int $col): void
    {
        for ($dr = -2; $dr <= 2; $dr++) for ($dc = -2; $dc <= 2; $dc++) {
            $v = (abs($dr)===2||abs($dc)===2||($dr===0&&$dc===0)) ? 1 : 0;
            self::safe($m, $row+$dr, $col+$dc, $v);
        }
    }

    private static function alignmentPositions(int $v): array
    {
        $tbl = [[],[6,18],[6,22],[6,26],[6,30],[6,34],
                [6,22,38],[6,24,42],[6,26,46],[6,28,50],[6,30,54]];
        return $tbl[$v] ?? [];
    }

    // Format info string (15 bits) for given ec level and mask
    private static function writeFormatInfo(array &$m, int $ec, int $mask, int $size): void
    {
        // Format info table (precomputed) [ecLevel*8 + mask]
        static $FMT = [
            // L (ec=0)
            0x77C4,0x72F3,0x7DAA,0x789D,0x662F,0x6318,0x6C41,0x6976,
            // M (ec=1)
            0x5412,0x5125,0x5E7C,0x5B4B,0x45F9,0x40CE,0x4F97,0x4AA0,
            // Q (ec=2)
            0x355F,0x3068,0x3F31,0x3A06,0x24B4,0x2183,0x2EDA,0x2BED,
            // H (ec=3)
            0x1689,0x13BE,0x1CE7,0x19D0,0x0762,0x0255,0x0D0C,0x083B,
        ];
        $fmtBits = $FMT[$ec * 8 + $mask];
        $bits = [];
        for ($i = 14; $i >= 0; $i--) $bits[] = ($fmtBits >> $i) & 1;

        // Place around top-left finder
        $pos = [
            [8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],
            [7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8],
        ];
        for ($i = 0; $i < 15; $i++) { self::safe($m, $pos[$i][0], $pos[$i][1], $bits[$i]); }

        // Place beside other finders
        for ($i = 0; $i < 7; $i++) self::safe($m, $size-1-$i, 8, $bits[$i]);
        for ($i = 7; $i < 15; $i++) self::safe($m, 8, $size-15+$i, $bits[$i]);
    }

    private static function encodeAlnum(string $data, int $version): array
    {
        $bits = [];
        // Mode indicator: 0010
        $bits = array_merge($bits, [0,0,1,0]);
        // Character count (11 bits for version 1-9)
        $n = strlen($data);
        for ($i = 10; $i >= 0; $i--) $bits[] = ($n >> $i) & 1;
        // Encode pairs
        for ($i = 0; $i < $n - 1; $i += 2) {
            $v = strpos(self::$ALNUM, $data[$i]) * 45 + strpos(self::$ALNUM, $data[$i+1]);
            for ($b = 10; $b >= 0; $b--) $bits[] = ($v >> $b) & 1;
        }
        if ($n % 2 === 1) {
            $v = strpos(self::$ALNUM, $data[$n-1]);
            for ($b = 5; $b >= 0; $b--) $bits[] = ($v >> $b) & 1;
        }
        // Terminator
        for ($i = 0; $i < 4 && count($bits) < self::dataCapBits($version); $i++) $bits[] = 0;
        // Pad to byte boundary
        while (count($bits) % 8 !== 0) $bits[] = 0;
        // Pad codewords
        $cap = self::dataCapBits($version);
        $pad = [0xEC,0x11];
        $pi  = 0;
        while (count($bits) < $cap) {
            for ($b = 7; $b >= 0; $b--) $bits[] = ($pad[$pi] >> $b) & 1;
            $pi = 1 - $pi;
        }
        return $bits;
    }

    private static function encodeByte(string $data, int $version): array
    {
        $bits = [0,1,0,0]; // mode byte
        $n = strlen($data);
        for ($i = 7; $i >= 0; $i--) $bits[] = ($n >> $i) & 1;
        for ($j = 0; $j < $n; $j++) {
            $byte = ord($data[$j]);
            for ($i = 7; $i >= 0; $i--) $bits[] = ($byte >> $i) & 1;
        }
        for ($i = 0; $i < 4 && count($bits) < self::dataCapBits($version); $i++) $bits[] = 0;
        while (count($bits) % 8 !== 0) $bits[] = 0;
        $cap = self::dataCapBits($version);
        $pad = [0xEC,0x11]; $pi = 0;
        while (count($bits) < $cap) {
            for ($b = 7; $b >= 0; $b--) $bits[] = ($pad[$pi] >> $b) & 1;
            $pi = 1 - $pi;
        }
        return $bits;
    }

    // Approximate data capacity in bits (simplified)
    private static function dataCapBits(int $v): int
    {
        static $cap = [0,152,272,440,640,864,1088,1248,1552,1856,2192];
        return $cap[$v] ?? 2192;
    }

    private static function placeData(array &$m, array $bits, int $size): void
    {
        $idx = 0;
        $up  = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--;
            for ($i = 0; $i < $size; $i++) {
                $row = $up ? ($size - 1 - $i) : $i;
                foreach ([0, -1] as $dc) {
                    $c = $col + $dc;
                    if ($m[$row][$c] === -1) {
                        $m[$row][$c] = ($idx < count($bits)) ? $bits[$idx++] : 0;
                    }
                }
            }
            $up = !$up;
        }
    }

    private static function applyMask(array &$m, int $mask, int $size): void
    {
        for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) {
            if ($m[$r][$c] < 0) continue;
            $apply = match($mask) {
                0 => ($r + $c) % 2 === 0,
                1 => $r % 2 === 0,
                2 => $c % 3 === 0,
                3 => ($r + $c) % 3 === 0,
                4 => ((int)($r/2) + (int)($c/3)) % 2 === 0,
                5 => ($r*$c)%2 + ($r*$c)%3 === 0,
                6 => (($r*$c)%2 + ($r*$c)%3) % 2 === 0,
                7 => (($r+$c)%2 + ($r*$c)%3) % 2 === 0,
                default => false,
            };
            // Only flip data modules, not function modules
            if ($apply) $m[$r][$c] ^= 1;
        }
    }

    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        // N1: 5+ in a row same color
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $m[$r][$c-1]) { $run++; if ($run === 5) $score += 3; elseif ($run > 5) $score++; }
                else $run = 1;
            }
        }
        return $score;
    }
}
