<?php
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin.php';
require_admin();
if (!is_post()) json_error('Method not allowed', 405);
verify_csrf_api();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Required fields
foreach (['name','sku','slug','price'] as $f) {
    if (empty(trim($body[$f] ?? ''))) json_error("Field '$f' is required.");
}

$id         = (int)($body['id'] ?? 0);
$name       = trim($body['name']);
$slug       = trim($body['slug']);
$sku        = trim($body['sku']);
$desc       = trim($body['description'] ?? '');
$price      = (float)$body['price'];
$salePrice  = strlen(trim($body['sale_price'] ?? '')) > 0 ? (float)$body['sale_price'] : null;
$stock      = max(0, (int)($body['stock_quantity'] ?? 0));
$catId      = (int)($body['category_id'] ?? 0) ?: null;
$badge      = in_array($body['badge'] ?? '', ['Hot','New','Sale']) ? $body['badge'] : null;
$isFeatured = (int)(bool)($body['is_featured'] ?? 0);
$isActive   = (int)(bool)($body['is_active'] ?? 1);
$rating     = min(5.0, max(0.0, (float)($body['rating'] ?? 0)));
$reviews    = max(0, (int)($body['review_count'] ?? 0));
$specs      = json_encode($body['specifications'] ?? new stdClass());

// Unique slug/sku guard
$db = db();
if ($id) {
    $dupSlug = $db->prepare("SELECT id FROM products WHERE slug=? AND id!=?");
    $dupSlug->execute([$slug, $id]);
    if ($dupSlug->fetch()) json_error('Slug already in use by another product.');
    $dupSku = $db->prepare("SELECT id FROM products WHERE sku=? AND id!=?");
    $dupSku->execute([$sku, $id]);
    if ($dupSku->fetch()) json_error('SKU already in use by another product.');

    $db->prepare("UPDATE products SET name=?,slug=?,description=?,sku=?,price=?,sale_price=?,
        stock_quantity=?,category_id=?,specifications=?,badge=?,is_featured=?,is_active=?,
        rating=?,review_count=? WHERE id=?")
       ->execute([$name,$slug,$desc,$sku,$price,$salePrice,$stock,$catId,$specs,
                  $badge,$isFeatured,$isActive,$rating,$reviews,$id]);
    json_success(['id' => $id], 'Product updated.');
} else {
    $dupSlug = $db->prepare("SELECT id FROM products WHERE slug=?");
    $dupSlug->execute([$slug]);
    if ($dupSlug->fetch()) json_error('Slug already in use.');
    $dupSku = $db->prepare("SELECT id FROM products WHERE sku=?");
    $dupSku->execute([$sku]);
    if ($dupSku->fetch()) json_error('SKU already in use.');

    $db->prepare("INSERT INTO products
        (name,slug,description,sku,price,sale_price,stock_quantity,category_id,
         specifications,badge,is_featured,is_active,rating,review_count)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$name,$slug,$desc,$sku,$price,$salePrice,$stock,$catId,
                  $specs,$badge,$isFeatured,$isActive,$rating,$reviews]);
    json_success(['id' => (int)$db->lastInsertId()], 'Product created.');
}
