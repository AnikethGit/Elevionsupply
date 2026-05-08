<?php
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!is_post()) json_error('Method not allowed', 405);

verify_csrf_api();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$userId   = $_SESSION['user_id'] ?? null;
$cartId   = get_or_create_cart();
$items    = get_cart_items($cartId);

if (empty($items)) json_error('Cart is empty');

// Validate card (mock)
$cardNumber = preg_replace('/\s+/', '', $body['card_number'] ?? '');
if ($cardNumber === '4000000000000002') json_error('Card declined. Please use a different card.');
if (strlen($cardNumber) < 13) json_error('Invalid card number');

// Calculate totals
$subtotal = array_sum(array_column($items, 'subtotal'));
$shipping = $subtotal >= 150 ? 0 : 9.99;
$tax      = round($subtotal * 0.08875, 2);
$total    = $subtotal + $shipping + $tax;

$ship = $body['shipping'] ?? [];
$db   = db();
$db->beginTransaction();

try {
    // ── Shipping address ──────────────────────────────────────────
    // For logged-in users, reuse an existing matching address rather
    // than inserting a duplicate row on every order.
    $shipAddrId = null;

    if ($userId) {
        $stmt = $db->prepare("
            SELECT id FROM addresses
            WHERE user_id = ?
              AND type IN ('shipping','both')
              AND first_name      = ?
              AND last_name       = ?
              AND street_address  = ?
              AND COALESCE(apt_suite,'')      = ?
              AND city            = ?
              AND COALESCE(state_province,'') = ?
              AND postal_code     = ?
              AND country         = ?
            LIMIT 1
        ");
        $stmt->execute([
            $userId,
            $ship['first_name'] ?? '',
            $ship['last_name']  ?? '',
            $ship['street']     ?? '',
            $ship['apt']        ?? '',
            $ship['city']       ?? '',
            $ship['state']      ?? '',
            $ship['zip']        ?? '',
            $ship['country']    ?? 'United States',
        ]);
        $existing = $stmt->fetch();
        if ($existing) {
            $shipAddrId = (int)$existing['id'];
        }
    }

    // Insert only when no match was found (or guest checkout)
    if (!$shipAddrId) {
        $stmt = $db->prepare("
            INSERT INTO addresses
                (user_id, type, first_name, last_name, street_address,
                 apt_suite, city, state_province, postal_code, country, phone)
            VALUES (?, 'shipping', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $ship['first_name'] ?? '',
            $ship['last_name']  ?? '',
            $ship['street']     ?? '',
            $ship['apt']        ?? '',
            $ship['city']       ?? '',
            $ship['state']      ?? '',
            $ship['zip']        ?? '',
            $ship['country']    ?? 'United States',
            $ship['phone']      ?? '',
        ]);
        $shipAddrId = (int)$db->lastInsertId();
    }

    // ── Create order ──────────────────────────────────────────────
    $orderNum = generate_order_number();
    $stmt = $db->prepare("
        INSERT INTO orders
            (user_id, order_number, status, subtotal, tax_amount,
             shipping_cost, total_amount, shipping_address_id,
             payment_method, payment_status)
        VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, 'credit_card', 'paid')
    ");
    $stmt->execute([$userId, $orderNum, $subtotal, $tax, $shipping, $total, $shipAddrId]);
    $orderId = (int)$db->lastInsertId();

    // ── Order items ───────────────────────────────────────────────
    $itemStmt = $db->prepare("
        INSERT INTO order_items
            (order_id, product_id, product_name, product_sku,
             quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $itemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['name'],
            $item['sku'],
            $item['quantity'],
            $item['unit_price'],
            $item['subtotal'],
        ]);
    }

    // ── Payment record ────────────────────────────────────────────
    $txnId = 'TXN-' . strtoupper(bin2hex(random_bytes(8)));
    $stmt  = $db->prepare("
        INSERT INTO payments
            (order_id, amount, method, status, transaction_id, card_last_four)
        VALUES (?, ?, 'credit_card', 'completed', ?, ?)
    ");
    $stmt->execute([$orderId, $total, $txnId, substr($cardNumber, -4)]);

    // ── Clear cart ────────────────────────────────────────────────
    $db->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
    $db->prepare("DELETE FROM carts WHERE id = ?")->execute([$cartId]);
    unset($_SESSION['cart_token']);

    $db->commit();

    json_success([
        'order_id'     => $orderId,
        'order_number' => $orderNum,
        'total'        => $total,
    ], 'Order placed successfully');

} catch (Exception $e) {
    $db->rollBack();
    json_error('Failed to process order. Please try again.', 500);
}
