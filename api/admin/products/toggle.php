<?php
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin.php';
require_admin();
if (!is_post()) redirect('/admin/products.php');
verify_csrf_form('/admin/products.php');

$id    = (int)post('id');
$field = post('field') === 'is_featured' ? 'is_featured' : 'is_active';
if (!$id) redirect('/admin/products.php');

$stmt = db()->prepare("SELECT $field FROM products WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if ($row) {
    db()->prepare("UPDATE products SET $field=? WHERE id=?")->execute([$row[$field] ? 0 : 1, $id]);
}
$ref = $_SERVER['HTTP_REFERER'] ?? '/admin/products.php';
redirect($ref);
