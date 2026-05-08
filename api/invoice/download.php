<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../lib/pdf.php';

/*
 * Access:
 *   Admin : ?order_id=N  (any order by DB id)
 *   Public: ?order=ORD-… (by order number, same trust as track page)
 */
$user    = auth_user();
$isAdmin = $user && $user['role'] === 'admin';

$order = null;
if ($isAdmin && !empty($_GET['order_id'])) {
    $order = get_order((int)$_GET['order_id']);
} elseif (!empty($_GET['order'])) {
    $stmt = db()->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([trim($_GET['order'])]);
    $row  = $stmt->fetch();
    if ($row) $order = get_order($row['id']);
}
if (!$order) { http_response_code(404); exit('Order not found.'); }

// ── Customer lookup ───────────────────────────────────────────────
$cu = null;
if ($order['user_id']) {
    $s = db()->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id=?");
    $s->execute([$order['user_id']]);
    $cu = $s->fetch() ?: null;
}
$addr = $order['shipping_address'];

// ── Helpers ───────────────────────────────────────────────────────
$fmt  = fn(float $v): string => '$'.number_format($v, 2);
$invN = 'INV-'.str_pad($order['id'], 5, '0', STR_PAD_LEFT);
$date = date('F j, Y', strtotime($order['created_at']));

// ── Colours ───────────────────────────────────────────────────────
const NR=22;  const NG=22;  const NB=63;    // navy  #16163F
const TR=86;  const TG=207; const TB=225;   // teal  #56CFE1
const GR=245; const GG=246; const GB=248;   // light grey rows

// ── Page constants ────────────────────────────────────────────────
$ML = 42;           // left margin
$MR = 553;          // right margin
$CW = $MR - $ML;    // content width  = 511

$pdf = new PDF();

// ═══════════════════════════════════════════════════════════════════
// HEADER  (y 0 → 90)
// ═══════════════════════════════════════════════════════════════════

// Top accent bar
$pdf->setFill(NR, NG, NB);
$pdf->fillRect(0, 0, PDF::W, 6);

// Brand – left column
$pdf->setFont(20, true);
$pdf->setTextColor(NR, NG, NB);
$pdf->text($ML, 26, 'ElevionSupply');

$pdf->setFont(8, false);
$pdf->setTextColor(100, 110, 120);
$pdf->text($ML, 42, 'hello@elevionsupply.com');
$pdf->text($ML, 54, '+1 (800) 555-TECH  ·  elevionsupply.com');

// INVOICE label + number – right column
$pdf->setFont(26, true);
$pdf->setTextColor(NR, NG, NB);
$pdf->text($ML, 26, 'INVOICE', 'R', $CW);

$pdf->setFont(9, false);
$pdf->setTextColor(100, 110, 120);
$pdf->text($ML, 44, $invN, 'R', $CW);
$pdf->text($ML, 56, 'Order Date:  '.$date, 'R', $CW);

// Full-width divider
$y = 72;
$pdf->setDraw(NR, NG, NB); $pdf->setLineWidth(1);
$pdf->line($ML, $y, $MR, $y);

// ═══════════════════════════════════════════════════════════════════
// META ROW  (y 78 → 118)   4 columns
// ═══════════════════════════════════════════════════════════════════
$y = 80;
$cols = [
    [$ML,       'INVOICE NUMBER', $invN],
    [$ML+128,   'ORDER NUMBER',   $order['order_number']],
    [$ML+280,   'DATE ISSUED',    $date],
    [$ML+408,   'STATUS',         strtoupper($order['status'])],
];
$pdf->setFont(7, false); $pdf->setTextColor(130, 130, 145);
foreach ($cols as [$x, $label,]) { $pdf->text($x, $y, $label); }
$y += 13;
$pdf->setFont(9, true); $pdf->setTextColor(NR, NG, NB);
foreach ($cols as [$x,, $val]) { $pdf->text($x, $y, $val); }

// Light divider
$y += 18;
$pdf->setDraw(210, 215, 225); $pdf->setLineWidth(0.5);
$pdf->line($ML, $y, $MR, $y);

// ═══════════════════════════════════════════════════════════════════
// BILL TO  /  SHIP TO  (y+12 → y+90)
// ═══════════════════════════════════════════════════════════════════
$y += 14;
$colL  = $ML;          // Bill To starts
$colR  = $ML + 256;    // Ship To starts

// Column headers
$pdf->setFont(7, true); $pdf->setTextColor(130, 130, 145);
$pdf->text($colL, $y, 'BILL TO');
$pdf->text($colR, $y, 'SHIP TO');
$y += 12;

// Resolve names and addresses
$billName  = $cu ? trim($cu['first_name'].' '.$cu['last_name']) : ($addr ? trim($addr['first_name'].' '.$addr['last_name']) : 'Guest');
$billEmail = $cu['email'] ?? '';
$billPhone = $cu['phone'] ?? '';
$shipName  = $addr ? trim($addr['first_name'].' '.$addr['last_name']) : $billName;
$shipLine1 = $addr ? $addr['street_address'].($addr['apt_suite'] ? ', '.$addr['apt_suite'] : '') : '';
$shipLine2 = $addr ? trim($addr['city'].', '.($addr['state_province'] ?? '').' '.($addr['postal_code'] ?? '')) : '';
$shipLine3 = $addr['country'] ?? '';

$pdf->setFont(10, true); $pdf->setTextColor(NR, NG, NB);
$pdf->text($colL, $y, $billName);
$pdf->text($colR, $y, $shipName);
$y += 13;

$pdf->setFont(8.5, false); $pdf->setTextColor(70, 75, 85);
$lineH = 12;

// Bill To lines
$bLines = array_filter([$billEmail, $billPhone]);
foreach ($bLines as $line) {
    $pdf->text($colL, $y, $line);
    $y += $lineH;
}

// Ship To lines (independent y)
$sy = $y - (count($bLines) * $lineH);
foreach (array_filter([$shipLine1, $shipLine2, $shipLine3]) as $line) {
    $pdf->text($colR, $sy, $line);
    $sy += $lineH;
}

// Advance y past whichever column is longer
$y = max($y, $sy) + 10;

// Light divider
$pdf->setDraw(210, 215, 225); $pdf->setLineWidth(0.5);
$pdf->line($ML, $y, $MR, $y);
$y += 2;

// ═══════════════════════════════════════════════════════════════════
// ITEMS TABLE
// Column layout (x positions):
//   QTY  : ML           → ML+36
//   DESC : ML+40        → ML+278  (238 wide)
//   SKU  : ML+282       → ML+380  (98 wide)
//   UNIT : ML+384       → ML+444  (60 wide, right-aligned)
//   TOTAL: ML+448       → MR      (right-aligned)
// ═══════════════════════════════════════════════════════════════════
$xQty   = $ML;
$xDesc  = $ML + 40;
$xSku   = $ML + 282;
$xUnit  = $ML + 384;
$xTotal = $MR;
$wUnit  = 60;
$wTotal = $MR - ($ML + 448);

$hdrH = 22;
$rowH = 22;

// Table header (navy)
$pdf->setFill(NR, NG, NB);
$pdf->fillRect($ML, $y, $CW, $hdrH);
$pdf->setFont(8, true); $pdf->setTextColor(255, 255, 255);
$hY = $y + 8;
$pdf->text($xQty+2,  $hY, 'QTY');
$pdf->text($xDesc,   $hY, 'DESCRIPTION');
$pdf->text($xSku,    $hY, 'SKU');
$pdf->text($xUnit,   $hY, 'UNIT PRICE', 'R', $wUnit);
$pdf->text($xTotal,  $hY, 'TOTAL',      'R', $MR - ($ML+448));
$y += $hdrH;

// Item rows
$pdf->setFont(8.5, false);
foreach ($order['items'] as $idx => $item) {
    if ($idx % 2 === 0) {
        $pdf->setFill(GR, GG, GB);
        $pdf->fillRect($ML, $y, $CW, $rowH);
    }
    $pdf->setTextColor(40, 45, 55);
    $rY = $y + 8;

    $name = $item['product_name'];
    if (mb_strlen($name) > 38) $name = mb_substr($name, 0, 36).'…';
    $sku  = $item['product_sku'] ?? '';
    if (mb_strlen($sku) > 14) $sku = mb_substr($sku, 0, 13).'…';

    $pdf->text($xQty+2,  $rY, (string)$item['quantity']);
    $pdf->text($xDesc,   $rY, $name);
    $pdf->text($xSku,    $rY, $sku);
    $pdf->text($xUnit,   $rY, $fmt((float)$item['unit_price']),               'R', $wUnit);

    $pdf->setFont(8.5, true);
    $pdf->text($xTotal,  $rY, $fmt((float)$item['unit_price'] * $item['quantity']), 'R', $MR - ($ML+448));
    $pdf->setFont(8.5, false);

    $y += $rowH;
}

// Bottom border of table
$pdf->setDraw(200, 205, 215); $pdf->setLineWidth(0.5);
$pdf->line($ML, $y, $MR, $y);

// ═══════════════════════════════════════════════════════════════════
// TOTALS  (right-aligned block, left half empty)
// ═══════════════════════════════════════════════════════════════════
$y += 10;
$tX  = $ML + 300;  // left edge of totals block
$tW  = $MR - $tX;  // width of totals block
$lW  = 100;        // label width within totals block
$vX  = $MR;        // right-align values here

$totRows = [
    ['Subtotal',     $fmt((float)$order['subtotal'])],
    ['Shipping',     (float)$order['shipping_cost'] === 0.0 ? 'FREE' : $fmt((float)$order['shipping_cost'])],
    ['Tax (8.875%)', $fmt((float)$order['tax_amount'])],
];

$pdf->setFont(9, false);
foreach ($totRows as [$lbl, $val]) {
    $pdf->setTextColor(90, 95, 108);
    $pdf->text($tX, $y, $lbl);
    $pdf->setTextColor(40, 45, 55);
    $pdf->text($vX, $y, $val, 'R', $tW);
    $y += 16;
}

// Thin line above grand total
$pdf->setDraw(200, 205, 215); $pdf->setLineWidth(0.5);
$pdf->line($tX, $y, $MR, $y);
$y += 2;

// Grand total row (navy fill)
$pdf->setFill(NR, NG, NB);
$pdf->fillRect($tX - 4, $y - 2, $tW + 4, 24);
$pdf->setFont(10, true); $pdf->setTextColor(255, 255, 255);
$pdf->text($tX + 4,   $y + 8, 'TOTAL DUE');
$pdf->setTextColor(TR, TG, TB);
$pdf->text($vX - 4,   $y + 8, $fmt((float)$order['total_amount']), 'R', $tW - 8);
$y += 32;

// ═══════════════════════════════════════════════════════════════════
// PAYMENT DETAILS  (left-aligned below totals)
// ═══════════════════════════════════════════════════════════════════
if ($order['payment']) {
    $pdf->setFont(7, true);  $pdf->setTextColor(130, 130, 145);
    $pdf->text($ML, $y, 'PAYMENT DETAILS');
    $y += 12;

    $method = ucwords(str_replace('_', ' ', $order['payment_method'] ?? ''));
    $status = ucfirst($order['payment_status'] ?? '');
    $txn    = $order['payment']['transaction_id'] ?? '';

    $pdf->setFont(8.5, false); $pdf->setTextColor(60, 65, 78);
    $pdf->text($ML, $y, 'Method: '.$method);
    $pdf->text($ML + 140, $y, 'Status: '.$status);
    if ($txn) { $pdf->text($ML + 260, $y, 'Txn: '.$txn); }
    $y += 16;
}

// ── Notes ─────────────────────────────────────────────────────────
if (!empty($order['notes'])) {
    $pdf->setFont(7, true);  $pdf->setTextColor(130, 130, 145);
    $pdf->text($ML, $y, 'NOTES');
    $y += 12;
    $pdf->setFont(8.5, false); $pdf->setTextColor(60, 65, 78);
    $pdf->text($ML, $y, mb_substr($order['notes'], 0, 110));
    $y += 16;
}

// ═══════════════════════════════════════════════════════════════════
// FOOTER  (pinned to page bottom)
// ═══════════════════════════════════════════════════════════════════
$fY = PDF::H - 46;

// Top accent line
$pdf->setDraw(NR, NG, NB); $pdf->setLineWidth(1);
$pdf->line(0, $fY, PDF::W, $fY);

$pdf->setFill(NR, NG, NB);
$pdf->fillRect(0, $fY, PDF::W, 46);

$pdf->setFont(8, false); $pdf->setTextColor(160, 175, 195);
$pdf->text($ML, $fY + 16,
    '123 Tech Plaza, San Francisco, CA 94102   ·   hello@elevionsupply.com   ·   +1 (800) 555-TECH');

$pdf->setFont(8.5, true); $pdf->setTextColor(TR, TG, TB);
$pdf->text($ML, $fY + 31, 'Thank you for your business!', 'C', $CW);

// ═══════════════════════════════════════════════════════════════════
// STREAM
// ═══════════════════════════════════════════════════════════════════
$bytes    = $pdf->output();
$filename = 'Invoice-'.$order['order_number'].'.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($bytes));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $bytes;
