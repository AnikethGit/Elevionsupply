<?php
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin.php';
require_admin();

if (!is_post()) json_error('Method not allowed', 405);
$rawInput = file_get_contents('php://input');
verify_csrf_api($rawInput);

$body = json_decode($rawInput, true) ?? [];

// Validate items
$items = array_values($body['items'] ?? []);
if (empty($items)) json_error('Add at least one product.');

// Validate shipping fields
foreach (['ship_first','ship_last','ship_street','ship_city','ship_zip'] as $f) {
    if (empty(trim($body[$f] ?? ''))) json_error("Missing required shipping field: $f");
}

$db = db();
$db->beginTransaction();
try {
    // ── Shipping address ──────────────────────────────────────────
    $userId = !empty($body['customer_id']) ? (int)$body['customer_id'] : null;
    $db->prepare("INSERT INTO addresses
        (user_id,type,first_name,last_name,street_address,apt_suite,city,state_province,postal_code,country,phone,email)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$userId,'shipping',
            trim($body['ship_first']), trim($body['ship_last']),
            trim($body['ship_street']), trim($body['ship_apt'] ?? ''),
            trim($body['ship_city']), trim($body['ship_state'] ?? ''),
            trim($body['ship_zip']), $body['ship_country'] ?? 'United States',
            trim($body['ship_phone'] ?? ''), trim($body['ship_email'] ?? '')]);
    $addrId = (int)$db->lastInsertId();

    // ── Fetch products and calculate totals ───────────────────────
    $subtotal = 0;
    $lines    = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = max(1, (int)($item['quantity'] ?? 1));
        $p   = get_product($pid);
        if (!$p) { $db->rollBack(); json_error("Product ID $pid not found."); }
        if ($qty > (int)$p['stock_quantity']) {
            $db->rollBack();
            json_error("Only {$p['stock_quantity']} of \"{$p['name']}\" in stock.");
        }
        $price    = isset($item['unit_price']) ? (float)$item['unit_price'] : $p['display_price'];
        $subtotal += $price * $qty;
        $lines[]  = ['product_id'=>$pid,'name'=>$p['name'],'sku'=>$p['sku'],'qty'=>$qty,'price'=>$price];
    }
    $shipping = max(0.0, (float)($body['shipping_cost'] ?? 0));
    $tax      = round($subtotal * 0.08875, 2);
    $total    = $subtotal + $shipping + $tax;

    // ── Create order ──────────────────────────────────────────────
    $orderNum       = generate_order_number();
    $paymentMethod  = $body['payment_method']  ?? 'credit_card';
    $paymentStatus  = $body['payment_status']  ?? 'paid';
    $orderStatus    = $body['order_status']    ?? 'pending';
    $notes          = trim($body['notes']      ?? '');
    $invoiceNumber  = trim($body['invoice_number'] ?? '');
    $orderDate      = !empty($body['order_date'])
                        ? date('Y-m-d H:i:s', strtotime($body['order_date']))
                        : date('Y-m-d H:i:s');

    $db->prepare("INSERT INTO orders
        (user_id,order_number,status,subtotal,tax_amount,shipping_cost,total_amount,
         shipping_address_id,payment_method,payment_status,notes,invoice_number,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$userId,$orderNum,$orderStatus,$subtotal,$tax,$shipping,$total,
                  $addrId,$paymentMethod,$paymentStatus,$notes,$invoiceNumber ?: null,$orderDate]);
    $orderId = (int)$db->lastInsertId();

    // ── Order items ───────────────────────────────────────────────
    $iStmt = $db->prepare("INSERT INTO order_items
        (order_id,product_id,product_name,product_sku,quantity,unit_price,subtotal)
        VALUES (?,?,?,?,?,?,?)");
    foreach ($lines as $l) {
        $iStmt->execute([$orderId,$l['product_id'],$l['name'],$l['sku'],$l['qty'],$l['price'],$l['price']*$l['qty']]);
        // Decrement stock; guard against concurrent overselling
        $dec = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
        $dec->execute([$l['qty'], $l['product_id'], $l['qty']]);
        if ($dec->rowCount() === 0) {
            throw new Exception("Insufficient stock for product ID {$l['product_id']} at time of order creation.");
        }
    }

    // ── Payment record ────────────────────────────────────────────
    $txnId     = 'ADM-' . strtoupper(bin2hex(random_bytes(6)));
    $cardLast4 = preg_replace('/\D/', '', $body['card_last_four'] ?? '');
    $cardLast4 = strlen($cardLast4) === 4 ? $cardLast4 : null;
    $db->prepare("INSERT INTO payments (order_id,amount,method,status,transaction_id,card_last_four)
        VALUES (?,?,?,?,?,?)")
       ->execute([$orderId,$total,$paymentMethod,$paymentStatus,$txnId,$cardLast4]);

    // ── Shipment record (tracking URL) ───────────────────────────
    $trackingUrl = trim($body['tracking_url'] ?? '');
    if ($trackingUrl) {
        $db->prepare("INSERT INTO shipments (order_id, tracking_url) VALUES (?,?)")
           ->execute([$orderId, $trackingUrl]);
    }

    $db->commit();
    json_success(['order_id' => $orderId, 'order_number' => $orderNum], 'Order created');

} catch (Exception $e) {
    $db->rollBack();
    json_error('Failed to create order: ' . $e->getMessage(), 500);
}
