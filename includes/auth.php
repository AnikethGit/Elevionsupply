<?php
require_once __DIR__ . '/config.php';

function auth_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT id, email, first_name, last_name, phone, role, notification_prefs FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) return null;
    $user['notification_prefs'] = $user['notification_prefs']
        ? json_decode($user['notification_prefs'], true)
        : ['order_updates' => true, 'shipping' => true, 'promotions' => false, 'wholesale' => false];
    return $user;
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(string $redirect = '/login.php'): void {
    if (!is_logged_in()) {
        header("Location: $redirect?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function login_user(string $email, string $password): array {
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'];
    $_SESSION['user_role'] = $user['role'];

    return ['success' => true, 'user' => [
        'id'         => $user['id'],
        'email'      => $user['email'],
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
        'role'       => $user['role'],
    ]];
}

function register_user(array $data): array {
    $email = strtolower(trim($data['email']));

    // Check existing
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    if (strlen($data['password']) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters'];
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = db()->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$email, $hash, trim($data['first_name']), trim($data['last_name']), $data['phone'] ?? null]);

    $userId = db()->lastInsertId();
    $_SESSION['user_id']    = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name']  = trim($data['first_name']);
    $_SESSION['user_role']  = 'customer';

    return ['success' => true, 'user_id' => $userId];
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}
