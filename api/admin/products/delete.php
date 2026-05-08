<?php
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin.php';
require_admin();
if (!is_post()) redirect('/admin/products.php');
verify_csrf_form('/admin/products.php');

$id = (int)post('id');
if (!$id) redirect('/admin/products.php');

// Check product isn't in any active orders
$inOrders = db()->prepare("SELECT COUNT(*) FROM order_items WHERE product_id=?");
$inOrders->execute([$id]);
if ((int)$inOrders->fetchColumn() > 0) {
    // Soft delete — deactivate rather than destroy referential integrity
    db()->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
} else {
    db()->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
}
redirect('/admin/products.php');
