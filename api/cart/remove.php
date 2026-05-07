<?php
require_once '../../includes/functions.php';

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$itemId = (int)($body['item_id'] ?? 0);
if (!$itemId) json_error('Item ID required');

$cartId = get_or_create_cart();
$stmt   = db()->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
$stmt->execute([$itemId, $cartId]);

$items = get_cart_items($cartId);
$total = array_sum(array_column($items, 'subtotal'));

json_success([
    'cart_count' => cart_count(),
    'cart_total' => cart_total(),
    'items'      => $items,
    'subtotal'   => $total,
]);
