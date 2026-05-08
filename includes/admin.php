<?php
require_once __DIR__ . '/auth.php';

/**
 * Abort with 403 if the current session is not an admin user.
 * Call this at the top of every admin-only page/endpoint.
 */
function require_admin(): void {
    if (!is_logged_in()) {
        redirect('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    $user = auth_user();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied.');
    }
}
