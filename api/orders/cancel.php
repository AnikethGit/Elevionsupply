<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_login();
if (!is_post()) redirect('/account/orders.php');

verify_csrf_form('/account/orders.php');

$orderId  = (int)post('order_id');
$userId   = $_SESSION['user_id'];
$redirect = post('redirect', '/account/orders.php');

$stmt = db()->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if ($order && in_array($order['status'], ['pending','processing'])) {
    db()->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$orderId]);
}
redirect($redirect);
