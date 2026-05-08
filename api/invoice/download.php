<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../lib/pdf.php';

/**
 * Access rules:
 *   - Admin:  ?order_id=N  (any order, by DB id)
 *   - Public: ?order=ORD-XXX  (by order number, no auth required — same as track page)
 */
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

// ── Build PDF ─────────────────────────────────────────────────────
$pdf = new PDF();

// Brand colours
define('NAVY_R', 22);  define('NAVY_G', 22);  define('NAVY_B', 63);
define('TEAL_R', 86);  define('TEAL_G', 207); define('TEAL_B', 225);
define('GOLD_R', 232); define('GOLD_G', 184); define('GOLD_B', 75);

$L  = 40;    // left margin
$R  = 555;   // right edge
$W  = $R - $L; // content width

// ── Header bar ───────────────────────────────────────────────────
$pdf->setFill(NAVY_R, NAVY_G, NAVY_B);
$pdf->fillRect(0, 0, PDF::W, 68);

$pdf->setTextColor(255, 255, 255);
$pdf->setFont(22, true); $pdf->text($L, 22, 'ElevionSupply');
$pdf->setFont(9, false);
$pdf->setTextColor(TEAL_R, TEAL_G, TEAL_B);
$pdf->text($L, 42, 'hello@elevionsupply.com   |   +1 (800) 555-TECH   |   elevionsupply.com');

// INVOICE label (right-aligned)
$pdf->setTextColor(TEAL_R, TEAL_G, TEAL_B);
$pdf->setFont(28, true); $pdf->text($L, 20, 'INVOICE', 'R', $W);
$pdf->setFont(9, false);
$pdf->setTextColor(180, 200, 215);
$invNum = 'INV-' . str_pad($order['id'], 5, '0', STR_PAD_LEFT);
$pdf->text($L, 44, $invNum, 'R', $W);

// ── Invoice meta block ────────────────────────────────────────────
$y = 88;
$pdf->setFont(8, false); $pdf->setTextColor(120, 120, 130);
$pdf->text($L, $y, 'INVOICE NUMBER');   $pdf->text(200, $y, 'DATE ISSUED');   $pdf->text(360, $y, 'ORDER NUMBER');   $pdf->text(480, $y, 'STATUS');
$y += 13;
$pdf->setFont(10, true); $pdf->setTextColor(NAVY_R, NAVY_G, NAVY_B);
$pdf->text($L, $y, $invNum);
$pdf->text(200, $y, date('M j, Y', strtotime($order['created_at'])));
$pdf->text(360, $y, $order['order_number']);
$pdf->text(480, $y, strtoupper($order['status']));

// Divider
$y += 18;
$pdf->setDraw(TEAL_R, TEAL_G, TEAL_B); $pdf->setLineWidth(1.5);
$pdf->line($L, $y, $R, $y);

// ── Bill To / Ship To ─────────────────────────────────────────────
$y += 14;
$pdf->setFont(8, false); $pdf->setTextColor(120, 120, 130);
$pdf->text($L, $y, 'BILL TO');
$pdf->text(300, $y, 'SHIP TO');

$y += 12;
$addr = $order['shipping_address'];

// Customer name
if ($order['user_id']) {
    $uStmt = db()->prepare("SELECT first_name, last_name, email FROM users WHERE id=?");
    $uStmt->execute([$order['user_id']]);
    $cu = $uStmt->fetch();
} else { $cu = null; }

$billName  = $cu ? $cu['first_name'].' '.$cu['last_name'] : ($addr ? $addr['first_name'].' '.$addr['last_name'] : 'Guest');
$billEmail = $cu ? $cu['email'] : '';

$pdf->setFont(10, true); $pdf->setTextColor(NAVY_R, NAVY_G, NAVY_B);
$pdf->text($L, $y, $billName);
$pdf->text(300, $y, $addr ? $addr['first_name'].' '.$addr['last_name'] : '');

$pdf->setFont(9, false); $pdf->setTextColor(80, 80, 90);
$y += 13;
if ($billEmail) { $pdf->text($L, $y, $billEmail); $y += 12; }
if ($addr) {
    $pdf->text(300, $y-($billEmail?12:0), $addr['street_address'].($addr['apt_suite']?', '.$addr['apt_suite']:''));
    $pdf->text(300, $y-($billEmail?0:-12)+($billEmail?0:12),
        $addr['city'].', '.$addr['state_province'].' '.$addr['postal_code']);
    $y2 = $y + ($billEmail ? 0 : 12);
    $pdf->text(300, $y2, $addr['country']);
}
$y += 20;

// ── Items table ───────────────────────────────────────────────────
$pdf->setFill(NAVY_R, NAVY_G, NAVY_B);
$pdf->fillRect($L, $y, $W, 20);
$pdf->setFont(9, true); $pdf->setTextColor(255, 255, 255);
$y2 = $y + 7;
$pdf->text($L+4,  $y2, 'DESCRIPTION');
$pdf->text(350,   $y2, 'SKU');
$pdf->text(420,   $y2, 'QTY',   'C', 40);
$pdf->text(460,   $y2, 'UNIT',  'R', 40);
$pdf->text(500,   $y2, 'TOTAL', 'R', 55);
$y += 20;

$rowH = 22;
$pdf->setFont(9, false);
foreach ($order['items'] as $idx => $item) {
    $bg = ($idx % 2 === 0);
    if ($bg) { $pdf->setFill(248, 249, 252); $pdf->fillRect($L, $y, $W, $rowH); }
    $pdf->setTextColor(40, 40, 50);
    $textY = $y + 8;
    $name  = strlen($item['product_name']) > 42 ? substr($item['product_name'],0,40).'…' : $item['product_name'];
    $pdf->text($L+4, $textY, $name);
    $pdf->text(350,  $textY, $item['product_sku'] ?? '');
    $pdf->text(420,  $textY, (string)$item['quantity'], 'C', 40);
    $pdf->text(460,  $textY, '$'.number_format($item['unit_price'],2), 'R', 40);
    $pdf->setFont(9, true);
    $pdf->text(500, $textY, '$'.number_format($item['unit_price']*$item['quantity'],2), 'R', 55);
    $pdf->setFont(9, false);
    $y += $rowH;
}

// Table bottom border
$pdf->setDraw(200, 210, 220); $pdf->setLineWidth(0.5);
$pdf->line($L, $y, $R, $y);
$y += 14;

// ── Totals block ──────────────────────────────────────────────────
$tX = 360; $tW = 195;
$rowItems = [
    ['Subtotal',       '$'.number_format($order['subtotal'],2)],
    ['Shipping',       (float)$order['shipping_cost'] === 0.0 ? 'FREE' : '$'.number_format($order['shipping_cost'],2)],
    ['Tax (8.875%)',   '$'.number_format($order['tax_amount'],2)],
];
$pdf->setFont(9, false);
foreach ($rowItems as [$lbl,$val]) {
    $pdf->setTextColor(90, 90, 100); $pdf->text($tX, $y, $lbl);
    $pdf->setTextColor(40, 40, 50);  $pdf->text($tX, $y, $val, 'R', $tW);
    $y += 15;
}

// Total row
$pdf->setFill(NAVY_R, NAVY_G, NAVY_B);
$pdf->fillRect($tX-4, $y-2, $tW+4, 22);
$pdf->setFont(11, true); $pdf->setTextColor(255,255,255);
$pdf->text($tX+4, $y+6, 'TOTAL DUE');
$pdf->setTextColor(TEAL_R, TEAL_G, TEAL_B);
$pdf->text($tX, $y+6, '$'.number_format($order['total_amount'],2), 'R', $tW-4);
$y += 30;

// ── Payment info ──────────────────────────────────────────────────
if ($order['payment']) {
    $pdf->setFont(8, false); $pdf->setTextColor(120,120,130);
    $pdf->text($L, $y, 'PAYMENT DETAILS');
    $y += 11;
    $pdf->setFont(9, false); $pdf->setTextColor(60,60,70);
    $method = ucwords(str_replace('_',' ',$order['payment_method'] ?? ''));
    $status = ucfirst($order['payment_status'] ?? '');
    $txn    = $order['payment']['transaction_id'] ?? '';
    $pdf->text($L, $y, "Method: $method   |   Status: $status" . ($txn ? "   |   Txn: $txn" : ''));
    $y += 16;
}

// ── Notes ─────────────────────────────────────────────────────────
if (!empty($order['notes'])) {
    $pdf->setFont(8, false); $pdf->setTextColor(120,120,130);
    $pdf->text($L, $y, 'NOTES');
    $y += 11;
    $pdf->setFont(9, false); $pdf->setTextColor(60,60,70);
    $pdf->text($L, $y, substr($order['notes'], 0, 100));
    $y += 16;
}

// ── Footer bar ────────────────────────────────────────────────────
$pdf->setFill(NAVY_R, NAVY_G, NAVY_B);
$pdf->fillRect(0, PDF::H - 42, PDF::W, 42);
$pdf->setFont(8, false); $pdf->setTextColor(150,170,190);
$pdf->text($L, PDF::H - 26, '123 Tech Plaza, San Francisco, CA 94102   |   hello@elevionsupply.com   |   +1 (800) 555-TECH');
$pdf->setTextColor(TEAL_R, TEAL_G, TEAL_B);
$pdf->text($L, PDF::H - 14, 'Thank you for your business!', 'C', $W);

// ── Stream PDF ────────────────────────────────────────────────────
$pdfBytes = $pdf->output();
$filename = 'Invoice-' . $order['order_number'] . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfBytes));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdfBytes;
