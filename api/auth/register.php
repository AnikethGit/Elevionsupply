<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
if (!is_post()) json_error('Method not allowed', 405);

verify_csrf_api();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$data = [
    'email'      => trim($body['email']      ?? post('email')),
    'password'   => $body['password']        ?? post('password'),
    'first_name' => trim($body['first_name'] ?? post('first_name')),
    'last_name'  => trim($body['last_name']  ?? post('last_name')),
    'phone'      => trim($body['phone']      ?? post('phone')),
];

if (!$data['email'] || !$data['password'] || !$data['first_name'] || !$data['last_name']) {
    json_error('All required fields must be filled');
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email address');
}

$result = register_user($data);
if ($result['success']) {
    json_success(['user_id' => $result['user_id']], 'Account created successfully');
} else {
    json_error($result['message']);
}
