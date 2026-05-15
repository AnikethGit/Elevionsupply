<?php
require_once '../../includes/functions.php';

if (!is_post()) json_error('Method not allowed', 405);

$rawInput = file_get_contents('php://input');
verify_csrf_api($rawInput);


$body = json_decode($rawInput, true) ?? [];
$productId  = (int)($body['product_id'] ?? 0);
$quantity   = max(1, (int)($body['quantity'] ?? 1));

if (!$productId) json_error('Product ID required');

$product = get_product($productId);
if (!$product) json_error('Product not found', 404);
if ($product['stock_quantity'] < 1) json_error('Out of stock');

$cartId = get_or_create_cart();

// Check existing item
$stmt = db()->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
$stmt->execute([$cartId, $productId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = db()->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE id = ?");
    $stmt->execute([$quantity, $existing['id']]);
} else {
    $price = $product['display_price'];
    $stmt  = db()->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    $stmt->execute([$cartId, $productId, $quantity, $price]);
}

json_success(['cart_count' => cart_count(), 'cart_total' => cart_total()], 'Added to cart');
