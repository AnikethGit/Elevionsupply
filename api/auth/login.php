<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_post()) json_error('Method not allowed', 405);

verify_csrf_api();

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($body['email'] ?? post('email'));
$pass  = $body['password'] ?? post('password');

if (!$email || !$pass) json_error('Email and password are required');

$result = login_user($email, $pass);
if ($result['success']) {
    json_success(['user' => $result['user']], 'Logged in successfully');
} else {
    json_error($result['message'], 401);
}
