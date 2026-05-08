<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../lib/pdf.php';

$user    = auth_user();
$isAdmin = $user && $user['role'] === 'admin';

$order = null;
if ($isAdmin && !empty($_GET['order_id'])) {
    $order = get_order((int)$_GET['order_id']);
} elseif (!empty($_GET['order'])) {
    $stmt = db()->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([trim($_GET['order'])]);
    $row = $stmt->fetch();
    if ($row) $order = get_order($row['id']);
}
if (!$order) { http_response_code(404); exit('Order not found.'); }

// Customer lookup
$cu = null;
if ($order['user_id']) {
    $s = db()->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id=?");
    $s->execute([$order['user_id']]);
    $cu = $s->fetch() ?: null;
}
$addr = $order['shipping_address'];

$fmt  = fn(float $v): string => '$'.number_format($v, 2);
$invN = 'INV-'.str_pad($order['id'], 5, '0', STR_PAD_LEFT);
$date = date('M j, Y', strtotime($order['created_at']));

// Colours
const NR=22;  const NG=22;  const NB=63;
const TR=86;  const TG=207; const TB=225;

// Layout constants — right edge = ML + CW = 553
// text(x, y, str, 'R', cw) => right edge of text = x + cw
$ML = 42;
$MR = 553;
$CW = $MR - $ML;   // 511

$pdf = new PDF();

// ── Top accent bar ────────────────────────────────────────────────
$pdf->setFill(NR, NG, NB);
$pdf->fillRect(0, 0, PDF::W, 5);

// ── Header ────────────────────────────────────────────────────────
// Left: brand
$pdf->setFont(20, true);
$pdf->setTextColor(NR, NG, NB);
$pdf->text($ML, 25, 'ElevionSupply');

$pdf->setFont(8, false);
$pdf->setTextColor(100, 110, 125);
$pdf->text($ML, 40, 'hello@elevionsupply.com');
$pdf->text($ML, 52, '+1 (800) 555-TECH  |  elevionsupply.com');

// Right: INVOICE label + number
// Right-align to $MR: call text($ML, y, str, 'R', $CW) => right edge = $ML + $CW = $MR
$pdf->setFont(26, true);
$pdf->setTextColor(NR, NG, NB);
$pdf->text($ML, 24, 'INVOICE', 'R', $CW);

$pdf->setFont(9, false);
$pdf->setTextColor(110, 115, 130);
$pdf->text($ML, 42, $invN, 'R', $CW);
$pdf->text($ML, 54, 'Order Date:  '.$date, 'R', $CW);

// Full-width divider
$y = 68;
$pdf->setDraw(NR, NG, NB); $pdf->setLineWidth(1);
$pdf->line($ML, $y, $MR, $y);

// ── Meta row ──────────────────────────────────────────────────────
// 4 columns at fixed x positions
$y = 76;
$mCols = [
    [$ML,       'INVOICE NUMBER', $invN],
    [$ML + 130, 'ORDER NUMBER',   $order['order_number']],
    [$ML + 285, 'DATE ISSUED',    $date],
    [$ML + 410, 'STATUS',         strtoupper($order['status'])],
];

$pdf->setFont(7, false); $pdf->setTextColor(135, 135, 150);
foreach ($mCols as [$x, $lbl,]) { $pdf->text($x, $y, $lbl); }
$y += 13;
$pdf->setFont(9, true); $pdf->setTextColor(NR, NG, NB);
foreach ($mCols as [$x,, $val]) { $pdf->text($x, $y, $val); }

// Light rule
$y += 18;
$pdf->setDraw(215, 218, 228); $pdf->setLineWidth(0.5);
$pdf->line($ML, $y, $MR, $y);

// ── Bill To / Ship To ─────────────────────────────────────────────
$y += 13;
$colL = $ML;        // left column
$colR = $ML + 256;  // right column

$pdf->setFont(7, true); $pdf->setTextColor(135, 135, 150);
$pdf->text($colL, $y, 'BILL TO');
$pdf->text($colR, $y, 'SHIP TO');
$y += 12;

$billName  = $cu ? trim($cu['first_name'].' '.$cu['last_name'])
                 : ($addr ? trim($addr['first_name'].' '.$addr['last_name']) : 'Guest');
$billEmail = trim($cu['email'] ?? '');
$billPhone = trim($cu['phone'] ?? '');
$shipName  = $addr ? trim($addr['first_name'].' '.$addr['last_name']) : $billName;
$shipLine1 = $addr ? $addr['street_address'].($addr['apt_suite'] ? ', '.$addr['apt_suite'] : '') : '';
$shipLine2 = $addr ? trim(($addr['city'] ?? '').', '.($addr['state_province'] ?? '').' '.($addr['postal_code'] ?? '')) : '';
$shipLine3 = $addr['country'] ?? '';

$lineH = 12;
$yL = $y;  // tracks bill-to column
$yR = $y;  // tracks ship-to column

$pdf->setFont(10, true); $pdf->setTextColor(NR, NG, NB);
$pdf->text($colL, $yL, $billName);  $yL += $lineH + 1;
$pdf->text($colR, $yR, $shipName);  $yR += $lineH + 1;

$pdf->setFont(8.5, false); $pdf->setTextColor(70, 76, 90);
foreach (array_filter([$billEmail, $billPhone]) as $line) {
    $pdf->text($colL, $yL, $line); $yL += $lineH;
}
foreach (array_filter([$shipLine1, $shipLine2, $shipLine3]) as $line) {
    // Truncate long address lines to fit in the column
    if (strlen($line) > 44) $line = substr($line, 0, 42).'...';
    $pdf->text($colR, $yR, $line); $yR += $lineH;
}

$y = max($yL, $yR) + 10;

$pdf->setDraw(215, 218, 228); $pdf->setLineWidth(0.5);
$pdf->line($ML, $y, $MR, $y);
$y += 2;

// ── Items table ───────────────────────────────────────────────────
//
//  Column definitions — ALL right-align calls use text(colX, y, str, 'R', colW)
//  so that right edge = colX + colW (never off-page).
//
//  QTY    : left=$ML,       width=32,   right=$ML+32   = 74
//  DESC   : left=$ML+36,    width=218,  right=$ML+254  = 296
//  SKU    : left=$ML+258,   width=105,  right=$ML+363  = 405
//  UNIT   : left=$ML+367,   width=72,   right=$ML+439  = 481
//  TOTAL  : left=$ML+443,   width=68,   right=$ML+511  = 553 = $MR

$cQtyX  = $ML;        $cQtyW  = 32;
$cDescX = $ML + 36;   // (left-aligned, no cw needed)
$cSkuX  = $ML + 258;  // (left-aligned)
$cUnitX = $ML + 367;  $cUnitW = 72;
$cTotX  = $ML + 443;  $cTotW  = $CW - 401;   // 511-401 = 110 → right edge = 553

$hdrH = 22; $rowH = 22;

// Header row
$pdf->setFill(NR, NG, NB);
$pdf->fillRect($ML, $y, $CW, $hdrH);
$pdf->setFont(8, true); $pdf->setTextColor(255, 255, 255);
$hY = $y + 8;
$pdf->text($cQtyX+3,  $hY, 'QTY');
$pdf->text($cDescX,   $hY, 'DESCRIPTION');
$pdf->text($cSkuX,    $hY, 'SKU');
$pdf->text($cUnitX,   $hY, 'UNIT PRICE', 'R', $cUnitW);
$pdf->text($cTotX,    $hY, 'TOTAL',      'R', $cTotW);
$y += $hdrH;

// Item rows
$pdf->setFont(8.5, false);
foreach ($order['items'] as $idx => $item) {
    if ($idx % 2 === 0) {
        $pdf->setFill(245, 246, 248);
        $pdf->fillRect($ML, $y, $CW, $rowH);
    }
    $pdf->setTextColor(42, 46, 58);
    $rY = $y + 8;

    $name = $item['product_name'];
    if (mb_strlen($name) > 36) $name = mb_substr($name, 0, 34).'...';
    $sku = $item['product_sku'] ?? '';
    if (mb_strlen($sku) > 14) $sku = mb_substr($sku, 0, 12).'...';

    $pdf->text($cQtyX+3, $rY, (string)$item['quantity']);
    $pdf->text($cDescX,  $rY, $name);
    $pdf->text($cSkuX,   $rY, $sku);
    $pdf->text($cUnitX,  $rY, $fmt((float)$item['unit_price']), 'R', $cUnitW);

    $pdf->setFont(8.5, true);
    $pdf->text($cTotX, $rY, $fmt((float)$item['unit_price'] * $item['quantity']), 'R', $cTotW);
    $pdf->setFont(8.5, false);

    $y += $rowH;
}

// Bottom border
$pdf->setDraw(200, 204, 215); $pdf->setLineWidth(0.5);
$pdf->line($ML, $y, $MR, $y);

// ── Totals ────────────────────────────────────────────────────────
//
//  Totals block right-side: label left, value right-aligned to $MR.
//  text(tX, y, val, 'R', tW)  =>  right edge = tX + tW = $MR
//
$y += 10;
$tX = $ML + 295;       // left edge of totals block
$tW = $MR - $tX;       // = 553 - 337 = 216

foreach ([
    ['Subtotal',     $fmt((float)$order['subtotal'])],
    ['Shipping',     (float)$order['shipping_cost'] === 0.0 ? 'FREE' : $fmt((float)$order['shipping_cost'])],
    ['Tax (8.875%)', $fmt((float)$order['tax_amount'])],
] as [$lbl, $val]) {
    $pdf->setFont(9, false);
    $pdf->setTextColor(90, 96, 110);  $pdf->text($tX + 4, $y, $lbl);
    $pdf->setTextColor(40, 45, 58);   $pdf->text($tX, $y, $val, 'R', $tW);
    $y += 16;
}

// Thin rule above grand total
$pdf->setDraw(200, 204, 215); $pdf->setLineWidth(0.5);
$pdf->line($tX, $y, $MR, $y);
$y += 2;

// Grand total
$pdf->setFill(NR, NG, NB);
$pdf->fillRect($tX - 2, $y - 1, $tW + 2, 24);
$pdf->setFont(10, true);
$pdf->setTextColor(255, 255, 255); $pdf->text($tX + 4,  $y + 8, 'TOTAL DUE');
$pdf->setTextColor(TR, TG, TB);    $pdf->text($tX,       $y + 8, $fmt((float)$order['total_amount']), 'R', $tW - 4);
$y += 32;

// ── Payment details ───────────────────────────────────────────────
if ($order['payment']) {
    $pdf->setFont(7, true); $pdf->setTextColor(135, 135, 150);
    $pdf->text($ML, $y, 'PAYMENT DETAILS');
    $y += 12;

    $method = ucwords(str_replace('_', ' ', $order['payment_method'] ?? ''));
    $pstat  = ucfirst($order['payment_status'] ?? '');
    $txn    = $order['payment']['transaction_id'] ?? '';

    $pdf->setFont(8.5, false); $pdf->setTextColor(60, 65, 80);
    $pdf->text($ML,       $y, 'Method: '.$method);
    $pdf->text($ML + 140, $y, 'Status: '.$pstat);
    if ($txn) { $pdf->text($ML + 260, $y, 'Txn: '.$txn); }
    $y += 16;
}

// ── Notes ─────────────────────────────────────────────────────────
if (!empty($order['notes'])) {
    $pdf->setFont(7, true); $pdf->setTextColor(135, 135, 150);
    $pdf->text($ML, $y, 'NOTES');
    $y += 12;
    $pdf->setFont(8.5, false); $pdf->setTextColor(60, 65, 80);
    $pdf->text($ML, $y, mb_substr($order['notes'], 0, 110));
}

// ── Footer (pinned to bottom) ─────────────────────────────────────
$fY = PDF::H - 44;
$pdf->setDraw(NR, NG, NB); $pdf->setLineWidth(1);
$pdf->line(0, $fY, PDF::W, $fY);

$pdf->setFill(NR, NG, NB);
$pdf->fillRect(0, $fY, PDF::W, 44);

$pdf->setFont(8, false); $pdf->setTextColor(155, 170, 192);
$pdf->text($ML, $fY + 14,
    '123 Tech Plaza, San Francisco, CA 94102   |   hello@elevionsupply.com   |   +1 (800) 555-TECH');

$pdf->setFont(9, true); $pdf->setTextColor(TR, TG, TB);
$pdf->text($ML, $fY + 29, 'Thank you for your business!', 'C', $CW);

// ── Stream ────────────────────────────────────────────────────────
$bytes    = $pdf->output();
$filename = 'Invoice-'.$order['order_number'].'.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($bytes));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $bytes;
