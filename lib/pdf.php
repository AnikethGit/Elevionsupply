<?php
/**
 * Minimal PDF generator for ElevionSupply invoices.
 * Pure PHP — no extensions or external files required.
 * Uses only built-in PDF fonts (Helvetica / Helvetica-Bold).
 */
class PDF {
    const W = 595.28;   // A4 width  (pt)
    const H = 841.89;   // A4 height (pt)

    private string $buf  = '';     // raw PDF bytes
    private array  $offs = [];     // byte offset of each indirect object
    private int    $n    = 0;      // last object number allocated
    private string $page = '';     // current page content stream

    // Drawing state
    private float  $sz   = 10;
    private bool   $bold = false;
    private string $fc   = '1 1 1 rg';   // fill color (white)
    private string $dc   = '0 0 0 RG';   // draw color (black)
    private string $tc   = '0 0 0 rg';   // text color (black)
    private float  $lw   = 0.5;

    // Helvetica character widths (units: 1/1000 of font size)
    private static array $CW = [
        ' '=>278,'!'=>278,'"'=>355,'#'=>556,'$'=>556,'%'=>889,'&'=>667,"'"=>191,
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

    // ── PDF object helpers ────────────────────────────────────────

    private function obj(): void {
        $this->offs[++$this->n] = strlen($this->buf);
        $this->buf .= "{$this->n} 0 obj\n";
    }

    private function end(): void { $this->buf .= "endobj\n"; }

    private function put(string $s): void { $this->buf .= "$s\n"; }

    // ── Coordinate conversion (our Y=0 is top of page) ───────────

    private function py(float $y): float { return self::H - $y; }

    // ── Escape a PDF string literal ───────────────────────────────

    private function esc(string $s): string {
        return str_replace(['\\','(',')'],['\\\\',"\\(","\\)"], $s);
    }

    // ── Text width in pt ─────────────────────────────────────────

    public function tw(string $s, float $sz = 0): float {
        if ($sz <= 0) $sz = $this->sz;
        $w = 0;
        foreach (str_split($s) as $c) {
            $w += self::$CW[$c] ?? 556;
        }
        return $w * $sz / 1000;
    }

    // ── Public API ────────────────────────────────────────────────

    /** Set font size; bold flag */
    public function setFont(float $sz, bool $bold = false): void {
        $this->sz   = $sz;
        $this->bold = $bold;
        $fn = $bold ? 'F2' : 'F1';
        $this->page .= "/$fn $sz Tf\n";
    }

    /** Fill color  (0–255) */
    public function setFill(int $r, int $g, int $b): void {
        $this->fc = sprintf('%.3f %.3f %.3f rg', $r/255, $g/255, $b/255);
    }

    /** Stroke/draw color (0–255) */
    public function setDraw(int $r, int $g, int $b): void {
        $this->dc = sprintf('%.3f %.3f %.3f RG', $r/255, $g/255, $b/255);
    }

    /** Text color (0–255) */
    public function setTextColor(int $r, int $g, int $b): void {
        $this->tc = sprintf('%.3f %.3f %.3f rg', $r/255, $g/255, $b/255);
    }

    public function setLineWidth(float $w): void {
        $this->lw   = $w;
        $this->page .= "$w w\n";
    }

    /** Draw a filled rectangle */
    public function fillRect(float $x, float $y, float $w, float $h): void {
        $py = $this->py($y + $h);
        $this->page .= "q {$this->fc} {$x} {$py} {$w} {$h} re f Q\n";
    }

    /** Draw a stroked rectangle */
    public function drawRect(float $x, float $y, float $w, float $h): void {
        $py = $this->py($y + $h);
        $this->page .= "q {$this->dc} {$x} {$py} {$w} {$h} re S Q\n";
    }

    /** Stroke a horizontal line */
    public function line(float $x1, float $y1, float $x2, float $y2): void {
        $py1 = $this->py($y1);
        $py2 = $this->py($y2);
        $this->page .= "q {$this->dc} {$this->lw} w {$x1} {$py1} m {$x2} {$py2} l S Q\n";
    }

    /**
     * Draw text at absolute position.
     * $align: 'L' | 'R' | 'C'  (L=default; for R/C supply total cell width $cw)
     */
    public function text(float $x, float $y, string $str,
                         string $align = 'L', float $cw = 0): void {
        if ($str === '') return;
        $py = $this->py($y);
        if ($align === 'R' && $cw > 0)       $x = $x + $cw - $this->tw($str);
        elseif ($align === 'C' && $cw > 0)   $x = $x + ($cw - $this->tw($str)) / 2;
        $esc = $this->esc($str);
        $fn  = $this->bold ? 'F2' : 'F1';
        $this->page .= "BT {$this->tc} /{$fn} {$this->sz} Tf {$x} {$py} Td ({$esc}) Tj ET\n";
    }

    /** Output the finished PDF as a string */
    public function output(): string {
        // ── Objects ──────────────────────────────────────────────
        $this->buf = "%PDF-1.4\n";

        // 1: Catalog
        $this->obj(); $this->put('<< /Type /Catalog /Pages 2 0 R >>'); $this->end();

        // 2: Pages (filled after page obj)
        $this->obj();
        $pagesPos = strlen($this->buf) - strlen("2 0 obj\n");
        $this->put('<< /Type /Pages /Kids [3 0 R] /Count 1 >>'); $this->end();

        // 3: Page
        $this->obj();
        $this->put('<< /Type /Page /Parent 2 0 R');
        $this->put("   /MediaBox [0 0 " . self::W . " " . self::H . "]");
        $this->put('   /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >>');
        $this->put('   /Contents 6 0 R >>');
        $this->end();

        // 4: Helvetica regular
        $this->obj();
        $this->put('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $this->end();

        // 5: Helvetica bold
        $this->obj();
        $this->put('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $this->end();

        // 6: Content stream
        $stream = $this->page;
        $len    = strlen($stream);
        $this->obj();
        $this->put("<< /Length $len >>");
        $this->put("stream");
        $this->buf .= $stream;
        $this->put("endstream");
        $this->end();

        // ── Cross-reference table ─────────────────────────────────
        $xref = strlen($this->buf);
        $this->buf .= "xref\n";
        $this->buf .= "0 " . ($this->n + 1) . "\n";
        $this->buf .= "0000000000 65535 f \n";
        foreach ($this->offs as $off) {
            $this->buf .= str_pad($off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        // ── Trailer ───────────────────────────────────────────────
        $this->buf .= "trailer\n<< /Size " . ($this->n + 1) . " /Root 1 0 R >>\n";
        $this->buf .= "startxref\n{$xref}\n%%EOF";

        return $this->buf;
    }
}
