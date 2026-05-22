<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../lib/pdf.php';
require_once '../../lib/qr.php';

/*
 * Access:
 *   Admin : ?order_id=N      (any order, by DB id)
 *   Public: ?order=ORD-XXX   (by order number — same trust as track page)
 *
 * Output: Two-page PDF
 *   Page 1 — Invoice
 *   Page 2 — Packing Slip (with QR codes)
 */

$user    = auth_user();
$isAdmin = $user && $user['role'] === 'admin';
$order   = null;

if ($isAdmin && !empty($_GET['order_id'])) {
    $order = get_order((int)$_GET['order_id']);
} elseif (!empty($_GET['order'])) {
    $stmt = db()->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([trim($_GET['order'])]);
    $row = $stmt->fetch();
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
$fmt  = fn(float $v): string => '£'.number_format($v, 2);
$invN = !empty($order['invoice_number'])
    ? $order['invoice_number']
    : 'INV-'.str_pad($order['id'], 5, '0', STR_PAD_LEFT);
$date = date('F j, Y', strtotime($order['created_at']));

// ── Resolved contact info ─────────────────────────────────────────
$billName  = $cu ? trim($cu['first_name'].' '.$cu['last_name'])
                 : ($addr ? trim($addr['first_name'].' '.$addr['last_name']) : 'Guest');
$billEmail = $cu['email'] ?? ($addr['email'] ?? '');
$billPhone = $cu['phone'] ?? ($addr['phone'] ?? '');
$shipName  = $addr ? trim($addr['first_name'].' '.$addr['last_name']) : $billName;
$shipLine1 = $addr ? $addr['street_address'].($addr['apt_suite'] ? ', '.$addr['apt_suite'] : '') : '';
$shipLine2 = $addr ? trim(($addr['city'] ?? '').', '.($addr['state_province'] ?? '').' '.($addr['postal_code'] ?? '')) : '';
$shipLine3 = $addr['country'] ?? 'United States';
$shipPhone = $addr['phone'] ?? $billPhone;
$shipEmail = $addr['email'] ?? $billEmail;

// ── Colours (RGB) ─────────────────────────────────────────────────
[$NR,$NG,$NB] = [22,  22,  63];   // navy
[$TR,$TG,$TB] = [86,  207, 225];  // teal
[$IR,$IG,$IB] = [24,  24,  27];   // near-black ink
[$MR,$MG,$MB] = [82,  82,  91];   // mid-grey
[$UR,$UG,$UB] = [161, 161, 170];  // muted
[$BR,$BG,$BB] = [228, 228, 231];  // border
[$AR,$AG,$AB] = [250, 250, 249];  // alt row bg

// ── Layout (points, A4) ───────────────────────────────────────────
$ML = 34;            // left margin (pt)
$MR_edge = 562;      // right edge
$CW = $MR_edge - $ML; // content width = 528 pt

$pdf = new PDF();
$pdf->setYOffset(6); // shift all text down 6pt — adjust as needed

// ═══════════════════════════════════════════════════════════════════
//  PAGE 1 — INVOICE
// ═══════════════════════════════════════════════════════════════════
$pdf->addPage();

// ── Top accent bar ────────────────────────────────────────────────
$pdf->setFill($NR,$NG,$NB);
$pdf->fillRect(0, 0, PDF::W, 5);

// ── Header: brand (left) / INVOICE label (right) ─────────────────
$pdf->setFont(20, true);
$pdf->setTextColor($NR,$NG,$NB);
$pdf->text($ML, 24, 'ElevionSupply');

$pdf->setFont(8, false);
$pdf->setTextColor($MR,$MG,$MB);
$pdf->text($ML, 40, 'hello@elevionsupply.com');
$pdf->text($ML, 52, '+1 518 644 1943  |  elevionsupply.com');
$pdf->text($ML, 64, '12 Highfield Road, Banchory, Aberdeenshire, AB31 5UN, United Kingdom');

$pdf->setFont(28, true);
$pdf->setTextColor($NR,$NG,$NB);
$pdf->text($ML, 24, 'INVOICE', 'R', $CW);

$pdf->setFont(9, false);
$pdf->setTextColor($MR,$MG,$MB);
$pdf->text($ML, 42, $invN, 'R', $CW);
$pdf->text($ML, 54, 'Order Date:  '.$date, 'R', $CW);

// Full-width divider
$y = 76;
$pdf->setDraw($NR,$NG,$NB); $pdf->setLineWidth(0.8);
$pdf->line($ML, $y, $MR_edge, $y);

// ── Meta row: 4 columns ───────────────────────────────────────────
$y = 84;
$mCols = [
    [$ML,       'INVOICE NUMBER', $invN],
    [$ML+133,   'ORDER NUMBER',   $order['order_number']],
    [$ML+290,   'DATE ISSUED',    $date],
    [$ML+422,   'STATUS',         strtoupper($order['status'])],
];
$pdf->setFont(7, false); $pdf->setTextColor($UR,$UG,$UB);
foreach ($mCols as [$x,$lbl,]) { $pdf->text($x, $y, $lbl); }
$y += 12;
$pdf->setFont(9, true); $pdf->setTextColor($IR,$IG,$IB);
foreach ($mCols as [$x,,$val]) { $pdf->text($x, $y, $val); }

// Light rule
$y += 18;
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.4);
$pdf->line($ML, $y, $MR_edge, $y);

// ── PARTIES: dark header bar ──────────────────────────────────────
$y += 1;
$colW3 = $CW / 3;   // each of 3 columns
$pdf->setFill($IR,$IG,$IB);
$pdf->fillRect($ML, $y, $CW, 16);
$pdf->setFont(7, true); $pdf->setTextColor(255,255,255);
$hY = $y + 5;
foreach (['BILL TO','SHIP TO','CUSTOMER PHONE'] as $i => $lbl) {
    $pdf->text($ML + $i * $colW3 + 5, $hY, $lbl);
}
// Vertical dividers in header
$pdf->setDraw(80,80,83); $pdf->setLineWidth(0.3);
$pdf->line($ML + $colW3,   $y, $ML + $colW3,   $y + 16);
$pdf->line($ML + 2*$colW3, $y, $ML + 2*$colW3, $y + 16);
$y += 16;

// ── PARTIES: content row ──────────────────────────────────────────
$pdf->setFill(255,255,255);
$rowStartY = $y;
$lineH = 11;
$bLines = array_filter([$billEmail, $billPhone]);
$sLines = array_filter([$shipLine1, $shipLine2, $shipLine3, $shipEmail]);
$rowH   = max(56, (count($bLines) + 2) * $lineH, (count($sLines) + 2) * $lineH, 60);
$pdf->fillRect($ML, $y, $CW, $rowH);
$method = ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'Credit Card'));

// Col1: Bill To
$cy  = $y + 8;
$cx1 = $ML + 5;
$pdf->setFont(9, true);  $pdf->setTextColor($IR,$IG,$IB);
$pdf->text($cx1, $cy, $billName); $cy += $lineH;
$pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
foreach (array_filter([$billEmail, $billPhone]) as $line) {
    $pdf->text($cx1, $cy, $line); $cy += $lineH - 1;
}

// Col2: Ship To
$cy  = $y + 8;
$cx2 = $ML + $colW3 + 5;
$pdf->setFont(9, true);  $pdf->setTextColor($IR,$IG,$IB);
$pdf->text($cx2, $cy, $shipName); $cy += $lineH;
$pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
foreach (array_filter([$shipLine1, $shipLine2, $shipLine3]) as $line) {
    if (strlen($line) > 38) $line = substr($line, 0, 36).'...';
    $pdf->text($cx2, $cy, $line); $cy += $lineH - 1;
}
if ($shipEmail) { $pdf->text($cx2, $cy, $shipEmail); }

// Col3: Phone / Payment Method
$cy  = $y + 8;
$cx3 = $ML + 2*$colW3 + 5;
$pdf->setFont(9, true);  $pdf->setTextColor($IR,$IG,$IB);
$pdf->text($cx3, $cy, $shipPhone ?: '—'); $cy += $lineH;
$pdf->setFont(7, true);  $pdf->setTextColor($UR,$UG,$UB);
$pdf->text($cx3, $cy, 'PAYMENT METHOD'); $cy += 9;
$pdf->setFont(8.5, true); $pdf->setTextColor($IR,$IG,$IB);
$pdf->text($cx3, $cy, $method);

// Vertical dividers in content row
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.4);
$pdf->line($ML + $colW3,   $rowStartY, $ML + $colW3,   $rowStartY + $rowH);
$pdf->line($ML + 2*$colW3, $rowStartY, $ML + 2*$colW3, $rowStartY + $rowH);

// Bottom border
$pdf->line($ML, $rowStartY + $rowH, $MR_edge, $rowStartY + $rowH);
$y = $rowStartY + $rowH + 1;

// ── ITEMS TABLE header bar ────────────────────────────────────────
// Columns: QTY | DESCRIPTION | UNIT PRICE | EST. TAX | SUBTOTAL
$cQtyW  = 30;  $cQtyX  = $ML;
$cDescX = $ML + 34;  $cDescW = $CW - 34 - 72 - 72 - 76;  // ~274
$cUnitX = $cDescX + $cDescW;  $cUnitW = 72;
$cTaxX  = $cUnitX + $cUnitW;  $cTaxW  = 72;
$cSubX  = $cTaxX  + $cTaxW;   $cSubW  = $MR_edge - $cSubX;

$hdrH = 18;
$pdf->setFill($IR,$IG,$IB);
$pdf->fillRect($ML, $y, $CW, $hdrH);
$pdf->setFont(7.5, true); $pdf->setTextColor(255,255,255);
$hY = $y + 6;
$pdf->text($cQtyX + 4, $hY, 'QTY');
$pdf->text($cDescX + 2, $hY, 'DESCRIPTION');
$pdf->text($cUnitX, $hY, 'UNIT PRICE', 'R', $cUnitW - 2);
$pdf->text($cTaxX,  $hY, 'EST. TAX',   'R', $cTaxW  - 2);
$pdf->text($cSubX,  $hY, 'SUBTOTAL',   'R', $cSubW  - 2);
// dividers in header
$pdf->setDraw(80,80,83); $pdf->setLineWidth(0.3);
foreach ([$cDescX,$cUnitX,$cTaxX,$cSubX] as $cx) {
    $pdf->line($cx, $y, $cx, $y + $hdrH);
}
$y += $hdrH;

// ── ITEMS ROWS ────────────────────────────────────────────────────
$taxRate  = 0.08875;
$nameLineH = 10;   // line height for wrapped name
foreach ($order['items'] as $idx => $item) {
    $price    = (float)$item['unit_price'];
    $qty      = (int)$item['quantity'];
    $lineSub  = $price * $qty;
    $lineTax  = round($lineSub * $taxRate, 2);
    $name     = $item['product_name'];
    $sku      = $item['product_sku'] ?? '';

    // Pre-calculate how many lines the name needs
    $descMaxW = $cDescW - 4;
    $pdf->setFont(8, false);
    $words    = explode(' ', $name);
    $lines    = 1; $testLine = '';
    foreach ($words as $w) {
        $test = $testLine === '' ? $w : "$testLine $w";
        if ($pdf->tw($test) <= $descMaxW) { $testLine = $test; }
        else { $lines++; $testLine = $w; }
    }
    $rowH2 = max(26, $lines * $nameLineH + ($sku ? 13 : 4) + 6);

    $bg = ($idx % 2 === 1) ? [$AR,$AG,$AB] : [255,255,255];
    $pdf->setFill(...$bg);
    $pdf->fillRect($ML, $y, $CW, $rowH2);

    $rY = $y + 6;

    // QTY — vertically centred
    $pdf->setFont(8, true);  $pdf->setTextColor($IR,$IG,$IB);
    $pdf->text($cQtyX + 4, $y + $rowH2/2 - 3, (string)$qty);

    // Name (wrapped)
    $pdf->setFont(8, false); $pdf->setTextColor($IR,$IG,$IB);
    $afterName = $pdf->multilineText($cDescX + 2, $rY, $name, $descMaxW, $nameLineH);

    // SKU below name
    if ($sku) {
        $pdf->setFont(7, false); $pdf->setTextColor($UR,$UG,$UB);
        $pdf->text($cDescX + 2, $afterName, $sku);
    }

    // Numeric columns — vertically centred in row
    $vY = $y + $rowH2/2 - 3;
    $pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
    $pdf->text($cUnitX, $vY, $fmt($price),   'R', $cUnitW - 2);
    $pdf->text($cTaxX,  $vY, $fmt($lineTax), 'R', $cTaxW  - 2);

    $pdf->setFont(8.5, true); $pdf->setTextColor($IR,$IG,$IB);
    $pdf->text($cSubX, $vY, $fmt($lineSub), 'R', $cSubW - 2);

    // Row divider + column lines
    $pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.3);
    $pdf->line($ML, $y + $rowH2, $MR_edge, $y + $rowH2);
    foreach ([$cDescX,$cUnitX,$cTaxX,$cSubX] as $cx) {
        $pdf->line($cx, $y, $cx, $y + $rowH2);
    }
    $y += $rowH2;
}

// ── TOTALS BLOCK ──────────────────────────────────────────────────
$y += 8;
$tX  = $ML + 300;
$tW  = $MR_edge - $tX;
$lbW = $tW * 0.55;  // label portion
$vaW = $tW * 0.45;  // value portion

$subtotal = (float)$order['subtotal'];
$taxAmt   = (float)$order['tax_amount'];
$shipAmt  = (float)$order['shipping_cost'];
$total    = (float)$order['total_amount'];

$totRows = [
    ['SUBTOTAL',                                   $fmt($subtotal), false],
    ['SALES TAX '.number_format($taxRate*100,2).'%', $fmt($taxAmt), false],
    ['SHIPPING & HANDLING',                         $shipAmt === 0.0 ? 'FREE' : $fmt($shipAmt), false],
    ['TOTAL',                                        $fmt($total),    true],
];

$tRowH = 16;
foreach ($totRows as [$lbl,$val,$isDark]) {
    if ($isDark) {
        $pdf->setFill($IR,$IG,$IB);
        $pdf->fillRect($tX, $y, $tW, $tRowH + 2);
        $pdf->setFont(8.5, true); $pdf->setTextColor(255,255,255);
        $pdf->text($tX, $y + 7, $lbl, 'R', $lbW - 2);
        $pdf->setDraw(80,80,83); $pdf->setLineWidth(0.3);
        $pdf->line($tX + $lbW, $y, $tX + $lbW, $y + $tRowH + 2);
        $pdf->setFont(9, true); $pdf->setTextColor($TR,$TG,$TB);
        $pdf->text($tX + $lbW, $y + 7, $val, 'R', $vaW - 2);
        $y += $tRowH + 2;
    } else {
        $pdf->setFill(255,255,255);
        $pdf->fillRect($tX, $y, $tW, $tRowH);
        $pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.3);
        $pdf->line($tX, $y + $tRowH, $tX + $tW, $y + $tRowH);
        $pdf->line($tX + $lbW, $y, $tX + $lbW, $y + $tRowH);
        $pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
        $pdf->text($tX, $y + 6, $lbl, 'R', $lbW - 2);
        $pdf->setFont(8, false); $pdf->setTextColor($IR,$IG,$IB);
        $pdf->text($tX + $lbW, $y + 6, $val, 'R', $vaW - 2);
        $y += $tRowH;
    }
}
// Left + right borders around non-dark rows
$lightH = 3 * $tRowH;
$lightY = $y - $lightH - ($tRowH + 2); // start of light rows
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.3);
$pdf->line($tX,      $lightY, $tX,      $lightY + $lightH);
$pdf->line($tX + $tW,$lightY, $tX + $tW,$lightY + $lightH);

// ── PAYMENT DETAILS ───────────────────────────────────────────────
$y += 12;
$pdf->setFont(7, true);  $pdf->setTextColor($UR,$UG,$UB);
$pdf->text($ML, $y, 'PAYMENT DETAILS');
$y += 11;
$pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
$pstat = ucfirst($order['payment_status'] ?? '');
$txn   = $order['payment']['transaction_id'] ?? '';
$cardLast4 = $order['payment']['card_last_four'] ?? '';
$methodDisplay = $method;
if (in_array($order['payment_method'] ?? '', ['credit_card','debit_card'])) {
    $methodDisplay .= $cardLast4 ? ' **** '.$cardLast4 : ' **** ----';
}
$pdf->text($ML, $y, 'Method: '.$methodDisplay);
$pdf->text($ML + 180, $y, 'Status: '.$pstat);
if ($txn) { $pdf->text($ML + 310, $y, 'Txn: '.$txn); }
$trackingUrl = $order['shipment']['tracking_url'] ?? '';
if ($trackingUrl) {
    $y += 13;
    $pdf->setFont(7, true); $pdf->setTextColor($UR,$UG,$UB);
    $pdf->text($ML, $y, 'TRACKING URL');
    $y += 11;
    $pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
    $display = strlen($trackingUrl) > 90 ? substr($trackingUrl, 0, 88).'...' : $trackingUrl;
    $pdf->text($ML, $y, $display);
}

// ── NOTES ─────────────────────────────────────────────────────────
if (!empty($order['notes'])) {
    $y += 20;
    $pdf->setFill($AR,$AG,$AB);
    $pdf->fillRect($ML, $y, $CW, 28);
    $pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.3);
    $pdf->drawRect($ML, $y, $CW, 28);
    $pdf->setFont(7, true); $pdf->setTextColor($UR,$UG,$UB);
    $pdf->text($ML + 4, $y + 8, 'NOTES');
    $pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
    $pdf->text($ML + 4, $y + 19, mb_substr($order['notes'], 0, 90));
}

// ── PAGE 1 FOOTER ─────────────────────────────────────────────────
$fY = PDF::H - 36;
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.4);
$pdf->line($ML, $fY, $MR_edge, $fY);
$pdf->setFont(7.5, false); $pdf->setTextColor($MR,$MG,$MB);
$pdf->text($ML, $fY + 12, '12 Highfield Road, Banchory, Aberdeenshire, AB31 5UN, United Kingdom');
$pdf->setFont(7.5, false); $pdf->setTextColor($UR,$UG,$UB);
$pdf->text($ML, $fY + 12,
    'hello@elevionsupply.com  |  +1 518 644 1943  |  elevionsupply.com',
    'R', $CW);

// ═══════════════════════════════════════════════════════════════════
//  PAGE 2 — PACKING SLIP
// ═══════════════════════════════════════════════════════════════════
$pdf->addPage();
$y = 0;

// ── Title bar ─────────────────────────────────────────────────────
$pdf->setFill($IR,$IG,$IB);
$pdf->fillRect(0, $y, PDF::W, 22);
$pdf->setFont(9, true); $pdf->setTextColor(255,255,255);
$pdf->text($ML, $y + 7, 'PACKING SLIP', 'C', $CW);
$y += 22;

// ── Top block: brand left / invoice meta right ────────────────────
$y += 6;
$blockY = $y;

$pdf->setFont(16, true); $pdf->setTextColor($NR,$NG,$NB);
$pdf->text($ML, $y + 8, 'ElevionSupply');
$pdf->setFont(7.5, false); $pdf->setTextColor($MR,$MG,$MB);
$pdf->text($ML, $y + 20, 'United States of America');
$pdf->text($ML, $y + 30, 'elevionsupply.com');

$pdf->setFont(14, true); $pdf->setTextColor($IR,$IG,$IB);
$pdf->text($ML, $y + 8, 'Invoice #'.$invN, 'R', $CW);
$pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
$pdf->text($ML, $y + 22, 'Order Date:  '.$date, 'R', $CW);

$y = max($blockY + 40, $y + 40);

// Divider
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.4);
$pdf->line($ML, $y, $MR_edge, $y);
$y += 10;

// ── SHIP TO ───────────────────────────────────────────────────────
$pdf->setFont(7, true); $pdf->setTextColor($UR,$UG,$UB);
$pdf->text($ML, $y, 'SHIP TO');
$y += 11;
$pdf->setFont(10, true); $pdf->setTextColor($IR,$IG,$IB);
$pdf->text($ML, $y, $shipName);
$y += 12;
$pdf->setFont(8.5, false); $pdf->setTextColor($MR,$MG,$MB);
foreach (array_filter([$shipLine1, $shipLine2, $shipLine3]) as $line) {
    $pdf->text($ML, $y, $line); $y += 11;
}
if ($shipPhone) { $pdf->text($ML, $y, $shipPhone); $y += 11; }
if ($shipEmail) { $pdf->text($ML, $y, $shipEmail); $y += 11; }

$y += 6;
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.4);
$pdf->line($ML, $y, $MR_edge, $y);
$y += 2;

// ── PACKING TABLE header ──────────────────────────────────────────
$descW2 = $CW - 36;
$qtyW2  = 36;

$pdf->setFill($IR,$IG,$IB);
$pdf->fillRect($ML, $y, $CW, 18);
$pdf->setFont(7.5, true); $pdf->setTextColor(255,255,255);
$pdf->text($ML + 4,           $y + 6, 'DESCRIPTION');
$pdf->text($ML + $descW2,     $y + 6, 'Q-TY', 'R', $qtyW2 - 2);
$pdf->setDraw(80,80,83); $pdf->setLineWidth(0.3);
$pdf->line($ML + $descW2, $y, $ML + $descW2, $y + 18);
$y += 18;

// ── PACKING TABLE rows ────────────────────────────────────────────
$rowH3 = 20;
foreach ($order['items'] as $idx => $item) {
    $bg3 = ($idx % 2 === 1) ? [$AR,$AG,$AB] : [255,255,255];
    $pdf->setFill(...$bg3);
    $pdf->fillRect($ML, $y, $CW, $rowH3);

    $name2 = $item['product_name'];
    if (strlen($name2) > 60) $name2 = substr($name2, 0, 58).'...';

    $pdf->setFont(8, false); $pdf->setTextColor($MR,$MG,$MB);
    $pdf->text($ML + 4, $y + 7, $name2);

    $pdf->setFont(9, true); $pdf->setTextColor($IR,$IG,$IB);
    $pdf->text($ML + $descW2, $y + 7, (string)$item['quantity'], 'R', $qtyW2 - 2);

    $pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.3);
    $pdf->line($ML, $y + $rowH3, $MR_edge, $y + $rowH3);
    $pdf->line($ML + $descW2, $y, $ML + $descW2, $y + $rowH3);
    $y += $rowH3;
}

// ── QR CODE SECTION ───────────────────────────────────────────────
$y += 8;
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.4);
$pdf->line($ML, $y, $MR_edge, $y);
$y += 1;

$qrAreaH = 100;
$qrSize  = 68;
$halfCW  = $CW / 2;

// Alt background
$pdf->setFill($AR,$AG,$AB);
$pdf->fillRect($ML, $y, $CW, $qrAreaH);
// Vertical divider between two QR cells
$pdf->setDraw($BR,$BG,$BB); $pdf->setLineWidth(0.4);
$pdf->line($ML + $halfCW, $y, $ML + $halfCW, $y + $qrAreaH);

// Left QR — ORDER NUMBER
$lqrX = $ML + ($halfCW - $qrSize) / 2;
$lqrY = $y + 14;
$pdf->setFont(6.5, true); $pdf->setTextColor($UR,$UG,$UB);
$pdf->text($ML, $y + 6, 'ORDER NUMBER', 'C', $halfCW);
try {
    $qrMatrix = QRCode::matrix($order['order_number'], 1);
    $pdf->qrCode($qrMatrix, $lqrX, $lqrY, $qrSize);
} catch (Throwable $e) {
    // Fallback: styled box
    $pdf->setFill($IR,$IG,$IB);
    $pdf->fillRect($lqrX, $lqrY, $qrSize, $qrSize);
    $pdf->setFont(7, true); $pdf->setTextColor(255,255,255);
    $pdf->text($ML, $lqrY + $qrSize/2 - 4, $order['order_number'], 'C', $halfCW);
}
$pdf->setFont(7.5, true); $pdf->setTextColor($IR,$IG,$IB);
$pdf->text($ML, $lqrY + $qrSize + 6, $order['order_number'], 'C', $halfCW);

// Right QR — TRACK YOUR ORDER
$rqrX = $ML + $halfCW + ($halfCW - $qrSize) / 2;
$rqrY = $lqrY;
$trackUrl  = $order['shipment']['tracking_url'] ?? '';
$trackStr  = $trackUrl ?: $order['order_number'];
$trackLabel = $trackUrl ?: ('#'.$order['order_number']);
$pdf->setFont(6.5, true); $pdf->setTextColor($UR,$UG,$UB);
$pdf->text($ML + $halfCW, $y + 6, 'TRACK YOUR ORDER', 'C', $halfCW);
try {
    $qrMatrix2 = QRCode::matrix($trackStr, 1);
    $pdf->qrCode($qrMatrix2, $rqrX, $rqrY, $qrSize);
} catch (Throwable $e) {
    $pdf->setFill($IR,$IG,$IB);
    $pdf->fillRect($rqrX, $rqrY, $qrSize, $qrSize);
    $pdf->setFont(7, true); $pdf->setTextColor(255,255,255);
    $pdf->text($ML + $halfCW, $rqrY + $qrSize/2 - 4, $trackLabel, 'C', $halfCW);
}
// Print URL (or order number) below QR — truncate long URLs
$pdf->setFont($trackUrl ? 6 : 7.5, true); $pdf->setTextColor($IR,$IG,$IB);
$displayLabel = strlen($trackLabel) > 52 ? substr($trackLabel, 0, 50).'...' : $trackLabel;
$pdf->text($ML + $halfCW, $rqrY + $qrSize + 6, $displayLabel, 'C', $halfCW);

$footerY2 = $y + $qrAreaH;

// ── PAGE 2 FOOTER dark bar ────────────────────────────────────────
$pdf->setFill($IR,$IG,$IB);
$pdf->fillRect(0, $footerY2, PDF::W, 24);
$pdf->setFont(7.5, true);  $pdf->setTextColor(255,255,255);
$pdf->text($ML, $footerY2 + 8, 'United States of America');
$pdf->setFont(7, false); $pdf->setTextColor(120,120,128);
$pdf->text($ML, $footerY2 + 8,
    'hello@elevionsupply.com  |  +1 518 644 1943', 'R', $CW);

// ═══════════════════════════════════════════════════════════════════
//  STREAM OUTPUT
// ═══════════════════════════════════════════════════════════════════
$bytes    = $pdf->output();
$filename = 'Invoice-'.$order['order_number'].'.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($bytes));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $bytes;
