<?php
require_once '../../includes/functions.php';

if (!is_post()) json_error('Method not allowed', 405);

$rawInput = file_get_contents('php://input');
verify_csrf_api($rawInput);


$body = json_decode($rawInput, true) ?? [];
$itemId   = (int)($body['item_id']  ?? 0);
$quantity = (int)($body['quantity'] ?? 0);

if (!$itemId) json_error('Item ID required');

$cartId = get_or_create_cart();

if ($quantity < 1) {
    $stmt = db()->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
    $stmt->execute([$itemId, $cartId]);
} else {
    // Look up the product's stock for this cart item
    $stmt = db()->prepare("
        SELECT p.stock_quantity
        FROM cart_items ci JOIN products p ON p.id = ci.product_id
        WHERE ci.id = ? AND ci.cart_id = ?
    ");
    $stmt->execute([$itemId, $cartId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Item not found in cart', 404);
    if ($quantity > (int)$row['stock_quantity']) {
        json_error('Only ' . $row['stock_quantity'] . ' in stock.');
    }

    $stmt = db()->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?");
    $stmt->execute([$quantity, $itemId, $cartId]);
}

$items = get_cart_items($cartId);
$total = array_sum(array_column($items, 'subtotal'));

json_success([
    'cart_count' => cart_count(),
    'cart_total' => cart_total(),
    'items'      => $items,
    'subtotal'   => $total,
]);
