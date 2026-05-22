<?php
/**
 * Multi-page PDF generator for ElevionSupply.
 * Pure PHP — no extensions required.
 * Fonts: Helvetica (regular + bold) via Type1.
 * Supports: multiple pages, fillRect, line, text (L/R/C), QR matrix rendering.
 */
class PDF
{
    const W = 595.28;   // A4 width  pt
    const H = 841.89;   // A4 height pt

    private string $buf   = '';
    private array  $offs  = [];
    private int    $n     = 0;
    private array  $pages = [];      // array of page content streams
    private int    $curPage = -1;    // index into $pages

    // Drawing state
    private float  $sz   = 10;
    private bool   $bold = false;
    private string $fc   = '1 1 1 rg';
    private string $dc   = '0 0 0 RG';
    private string $tc   = '0 0 0 rg';
    private float  $lw   = 0.5;
    private float  $yOffset = 0.0;
    public function setYOffset(float $o): void { $this->yOffset = $o; }

    // Helvetica char widths (1/1000 em)
    private static array $CW = [
        ' '=>278,'!'=>278,'"'=>355,'#'=>556,'$'=>556,'%'=>889,'&'=>667,"'"=>191,
        '£'=>556,
        '('=>333,')'=>333,'*'=>389,'+'=>584,','=>278,'-'=>333,'.'=>278,'/'=>278,
        '0'=>556,'1'=>556,'2'=>556,'3'=>556,'4'=>556,'5'=>556,'6'=>556,'7'=>556,
        '8'=>556,'9'=>556,':'=>278,';'=>278,'<'=>584,'='=>584,'>'=>584,'?'=>556,
        '@'=>1015,'A'=>667,'B'=>667,'C'=>722,'D'=>722,'E'=>667,'F'=>611,'G'=>778,
        'H'=>722,'I'=>278,'J'=>500,'K'=>667,'L'=>556,'M'=>833,'N'=>722,'O'=>778,
        'P'=>667,'Q'=>778,'R'=>722,'S'=>667,'T'=>611,'U'=>722,'V'=>667,'W'=>944,
        'X'=>667,'Y'=>667,'Z'=>611,'['=>278,'\\'=>278,']'=>278,'^'=>469,'_'=>556,
        '`'=>333,'a'=>556,'b'=>556,'c'=>500,'d'=>556,'e'=>556,'f'=>278,'g'=>556,
        'h'=>556,'i'=>222,'j'=>222,'k'=>500,'l'=>222,'m'=>833,'n'=>556,'o'=>556,
        'p'=>556,'q'=>556,'r'=>333,'s'=>500,'t'=>278,'u'=>556,'v'=>500,'w'=>722,
        'x'=>500,'y'=>500,'z'=>500,'{'=>334,'|'=>260,'}'=>334,'~'=>584,
    ];

    // ── Page management ───────────────────────────────────────────

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->curPage = count($this->pages) - 1;
        // Reset to default state each page
        $this->sz = 10; $this->bold = false;
        $this->fc = '1 1 1 rg'; $this->dc = '0 0 0 RG';
        $this->tc = '0 0 0 rg'; $this->lw = 0.5;
    }

    private function p(string $s): void
    {
        $this->pages[$this->curPage] .= $s . "\n";
    }

    private function py(float $y): float { return self::H - $y; }

    private function esc(string $s): string
    {
        return str_replace(['\\','(',')'],['\\\\',"\\(","\\)"], $s);
    }

    // ── Font / colour state ───────────────────────────────────────

    public function setFont(float $sz, bool $bold = false): void
    {
        $this->sz = $sz; $this->bold = $bold;
        $fn = $bold ? 'F2' : 'F1';
        $this->p("/$fn $sz Tf");
    }

    public function setFill(int $r, int $g, int $b): void
    {
        $this->fc = sprintf('%.3f %.3f %.3f rg', $r/255,$g/255,$b/255);
    }

    public function setDraw(int $r, int $g, int $b): void
    {
        $this->dc = sprintf('%.3f %.3f %.3f RG', $r/255,$g/255,$b/255);
    }

    public function setTextColor(int $r, int $g, int $b): void
    {
        $this->tc = sprintf('%.3f %.3f %.3f rg', $r/255,$g/255,$b/255);
    }

    public function setLineWidth(float $w): void
    {
        $this->lw = $w;
        $this->p("$w w");
    }

    // ── Drawing primitives ────────────────────────────────────────

    public function fillRect(float $x, float $y, float $w, float $h): void
    {
        $py = $this->py($y + $h);
        $this->p("q {$this->fc} {$x} {$py} {$w} {$h} re f Q");
    }

    public function drawRect(float $x, float $y, float $w, float $h): void
    {
        $py = $this->py($y + $h);
        $this->p("q {$this->dc} {$this->lw} w {$x} {$py} {$w} {$h} re S Q");
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $py1 = $this->py($y1); $py2 = $this->py($y2);
        $this->p("q {$this->dc} {$this->lw} w {$x1} {$py1} m {$x2} {$py2} l S Q");
    }

    /** text width in pt */
    public function tw(string $s, float $sz = 0): float
    {
        if ($sz <= 0) $sz = $this->sz;
        $w = 0;
        foreach (str_split($s) as $c) { $w += self::$CW[$c] ?? 556; }
        return $w * $sz / 1000;
    }

    /**
     * Draw text. align: L|R|C  (for R/C pass cell width $cw)
     */
    public function text(float $x, float $y, string $str,
                         string $align = 'L', float $cw = 0): void
    {
        if ($str === '') return;
        $py = $this->py($y + $this->yOffset);
        if ($align === 'R' && $cw > 0)     $x = $x + $cw - $this->tw($str);
        elseif ($align === 'C' && $cw > 0) $x = $x + ($cw - $this->tw($str)) / 2;
        $esc = $this->esc($str);
        $fn  = $this->bold ? 'F2' : 'F1';
        $this->p("BT {$this->tc} /{$fn} {$this->sz} Tf {$x} {$py} Td ({$esc}) Tj ET");
    }

    /**
     * Draw multi-line text that wraps within $maxW pt.
     * Returns the Y position after the last line.
     */
    public function multilineText(float $x, float $y, string $str,
                                   float $maxW, float $lineH = 0): float
    {
        if ($lineH <= 0) $lineH = $this->sz * 1.4;
        $words = explode(' ', $str);
        $line  = '';
        foreach ($words as $word) {
            $test = $line === '' ? $word : "$line $word";
            if ($this->tw($test) <= $maxW) {
                $line = $test;
            } else {
                if ($line !== '') { $this->text($x, $y, $line); $y += $lineH; }
                // If single word wider than column, just print it (will clip)
                $line = $word;
            }
        }
        if ($line !== '') { $this->text($x, $y, $line); $y += $lineH; }
        return $y;
    }
     * @param array $matrix  2D bool array from QRCode::matrix()
     * @param float $x       top-left x (pt)
     * @param float $y       top-left y (pt)
     * @param float $size    total size (pt) — module size = size/count
     */
    public function qrCode(array $matrix, float $x, float $y, float $size): void
    {
        $n   = count($matrix);
        if ($n === 0) return;
        $mod = $size / $n;

        // White background
        $this->setFill(255, 255, 255);
        $this->fillRect($x, $y, $size, $size);

        // Dark modules
        $this->setFill(24, 24, 27);
        foreach ($matrix as $row => $cols) {
            foreach ($cols as $col => $dark) {
                if (!$dark) continue;
                $this->fillRect(
                    $x + $col * $mod,
                    $y + $row * $mod,
                    $mod, $mod
                );
            }
        }
    }

    // ── Serialise to PDF bytes ────────────────────────────────────

    public function output(): string
    {
        $pageCount = count($this->pages);
        $this->buf = "%PDF-1.4\n";

        // Collect page object numbers (assigned in order)
        // Objects: 1=Catalog, 2=Pages, 3..=(3+n-1)=PageN, then fonts, then streams
        $pageObjStart = 3;
        $fontObjStart = $pageObjStart + $pageCount;       // F1, F2
        $streamStart  = $fontObjStart + 2;                // one stream per page

        $pageObjNums   = range($pageObjStart, $pageObjStart + $pageCount - 1);
        $streamObjNums = range($streamStart, $streamStart + $pageCount - 1);

        $kids = implode(' ', array_map(fn($n)=>"$n 0 R", $pageObjNums));

        // ── Object 1: Catalog
        $this->offs[1] = strlen($this->buf);
        $this->buf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // ── Object 2: Pages
        $this->offs[2] = strlen($this->buf);
        $this->buf .= "2 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>\nendobj\n";

        // ── Objects 3..N: Page dictionaries
        foreach ($pageObjNums as $i => $pn) {
            $sn = $streamObjNums[$i];
            $this->offs[$pn] = strlen($this->buf);
            $this->buf .= "{$pn} 0 obj\n";
            $this->buf .= "<< /Type /Page /Parent 2 0 R\n";
            $this->buf .= "   /MediaBox [0 0 " . self::W . " " . self::H . "]\n";
            $this->buf .= "   /Resources << /Font << /F1 {$fontObjStart} 0 R /F2 " . ($fontObjStart+1) . " 0 R >> >>\n";
            $this->buf .= "   /Contents {$sn} 0 R >>\n";
            $this->buf .= "endobj\n";
        }

        // ── Font objects
        $this->offs[$fontObjStart] = strlen($this->buf);
        $this->buf .= "{$fontObjStart} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $this->offs[$fontObjStart+1] = strlen($this->buf);
        $this->buf .= ($fontObjStart+1) . " 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        // ── Content streams
        foreach ($streamObjNums as $i => $sn) {
            $stream = $this->pages[$i];
            $len    = strlen($stream);
            $this->offs[$sn] = strlen($this->buf);
            $this->buf .= "{$sn} 0 obj\n<< /Length {$len} >>\nstream\n{$stream}endstream\nendobj\n";
        }

        $totalObjs = max(array_merge(array_keys($this->offs), $streamObjNums)) + 1;

        // ── xref
        $xref = strlen($this->buf);
        $this->buf .= "xref\n0 {$totalObjs}\n";
        $this->buf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjs; $i++) {
            $off = $this->offs[$i] ?? 0;
            $this->buf .= str_pad($off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $this->buf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\n";
        $this->buf .= "startxref\n{$xref}\n%%EOF";

        return $this->buf;
    }
}
