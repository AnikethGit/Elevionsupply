<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_login();

if (!is_post()) redirect('/account/settings.php');

verify_csrf_form('/account/settings.php');

$userId = (int)$_SESSION['user_id'];

$db = db();
$db->beginTransaction();
try {
    // Orders keep their records (user_id goes NULL via ON DELETE SET NULL in schema)
    // Cascade-delete: sessions, addresses, cart items handled by FK ON DELETE CASCADE
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['flash_error'] = 'Account deletion failed. Please try again.';
    redirect('/account/settings.php');
}

logout_user();
redirect('/');
