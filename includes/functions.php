<?php
require_once __DIR__ . '/config.php';

// ─── JSON Response ────────────────────────────────────────────────
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $code = 400): void {
    json_response(['success' => false, 'message' => $message], $code);
}

function json_success(array $data = [], string $message = 'OK'): void {
    json_response(array_merge(['success' => true, 'message' => $message], $data));
}

// ─── CSRF ─────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Verify CSRF for form POST (from $_POST) or JSON API (from X-CSRF-Token header).
 * Calls json_error() and exits on failure — use in API endpoints.
 *
 * Pass $rawBody if you have already read php://input, so the stream
 * isn't consumed a second time. If omitted and the header is absent,
 * falls back to reading php://input once.
 */
function verify_csrf_api(?string $rawBody = null): void {
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } else {
        // Only read php://input if caller hasn't provided it
        $raw   = $rawBody ?? file_get_contents('php://input');
        $token = json_decode($raw, true)['csrf_token'] ?? null;
    }

    if (!verify_csrf($token)) {
        json_error('Invalid or missing CSRF token', 403);
    }
}

/**
 * Verify CSRF for standard HTML form POSTs.
 * Redirects to $redirect on failure — use in page-level form handlers.
 */
function verify_csrf_form(string $redirect = '/'): void {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'Security token mismatch. Please try again.';
        header("Location: $redirect");
        exit;
    }
}

// ─── Cart ─────────────────────────────────────────────────────────
function get_or_create_cart(): int {
    $userId      = $_SESSION['user_id'] ?? null;
    $sessionToken = $_SESSION['cart_token'] ?? null;

    if ($userId) {
        $stmt = db()->prepare("SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();
        if ($cart) return $cart['id'];

        $stmt = db()->prepare("INSERT INTO carts (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        return (int)db()->lastInsertId();
    }

    if (!$sessionToken) {
        $sessionToken = bin2hex(random_bytes(16));
        $_SESSION['cart_token'] = $sessionToken;
    }

    $stmt = db()->prepare("SELECT id FROM carts WHERE session_token = ?");
    $stmt->execute([$sessionToken]);
    $cart = $stmt->fetch();
    if ($cart) return $cart['id'];

    $stmt = db()->prepare("INSERT INTO carts (session_token) VALUES (?)");
    $stmt->execute([$sessionToken]);
    return (int)db()->lastInsertId();
}

function get_cart_items(int $cartId): array {
    $stmt = db()->prepare("
        SELECT ci.id, ci.quantity, ci.unit_price,
               p.id AS product_id, p.name, p.sku, p.images
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = ?
    ");
    $stmt->execute([$cartId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['subtotal'] = $item['unit_price'] * $item['quantity'];
        $item['images']   = json_decode($item['images'] ?? '[]', true);
    }
    return $items;
}

function cart_count(): int {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['cart_token'])) return 0;
    $cartId = get_or_create_cart();
    $stmt   = db()->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return (int)$stmt->fetchColumn();
}

function cart_total(): float {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['cart_token'])) return 0;
    $cartId = get_or_create_cart();
    $stmt   = db()->prepare("SELECT COALESCE(SUM(unit_price * quantity), 0) FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return (float)$stmt->fetchColumn();
}

// ─── Products ─────────────────────────────────────────────────────
function get_products(array $filters = [], int $page = 1, int $limit = 12): array {
    $where  = ['p.is_active = 1'];
    $params = [];

    if (!empty($filters['search'])) {
        $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }
    if (!empty($filters['category'])) {
        $where[]  = "c.slug = ?";
        $params[] = $filters['category'];
    }
    if (!empty($filters['min_price'])) {
        $where[]  = "COALESCE(p.sale_price, p.price) >= ?";
        $params[] = (float)$filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $where[]  = "COALESCE(p.sale_price, p.price) <= ?";
        $params[] = (float)$filters['max_price'];
    }
    if (!empty($filters['is_featured'])) {
        $where[]  = "p.is_featured = 1";
    }

    $order = match ($filters['sort'] ?? '') {
        'price-asc'  => 'COALESCE(p.sale_price, p.price) ASC',
        'price-desc' => 'COALESCE(p.sale_price, p.price) DESC',
        'rating'     => 'p.rating DESC',
        default      => 'p.created_at DESC',
    };

    $whereStr = implode(' AND ', $where);
    $offset   = ($page - 1) * $limit;

    $countStmt = db()->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = db()->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $whereStr
        ORDER BY $order
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    foreach ($products as &$p) {
        $p['images']         = json_decode($p['images'] ?? '[]', true);
        $p['specifications'] = json_decode($p['specifications'] ?? '{}', true);
        $p['display_price']  = $p['sale_price'] ?? $p['price'];
    }

    return [
        'data'       => $products,
        'pagination' => [
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => (int)ceil($total / $limit),
        ]
    ];
}

function get_product(int $id): ?array {
    $stmt = db()->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) return null;
    $p['images']         = json_decode($p['images'] ?? '[]', true);
    $p['specifications'] = json_decode($p['specifications'] ?? '{}', true);
    $p['display_price']  = $p['sale_price'] ?? $p['price'];
    return $p;
}

function get_categories(): array {
    $stmt = db()->query("SELECT * FROM categories ORDER BY name ASC");
    return $stmt->fetchAll();
}

// ─── Orders ───────────────────────────────────────────────────────
function generate_order_number(): string {
    return 'ORD-' . time() . rand(100, 999);
}

function get_orders(int $userId, array $filters = [], int $page = 1): array {
    $where  = ['o.user_id = ?'];
    $params = [$userId];

    if (!empty($filters['status'])) {
        $where[]  = "o.status = ?";
        $params[] = $filters['status'];
    }

    $whereStr = implode(' AND ', $where);
    $limit    = 10;
    $offset   = ($page - 1) * $limit;

    $countStmt = db()->prepare("SELECT COUNT(*) FROM orders o WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = db()->prepare("SELECT * FROM orders o WHERE $whereStr ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Fetch all items for all orders in one query instead of N separate queries
    if (!empty($orders)) {
        $ids          = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $itemsStmt    = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders)");
        $itemsStmt->execute($ids);

        $itemsByOrder = [];
        foreach ($itemsStmt->fetchAll() as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
        unset($order);
    }

    return ['data' => $orders, 'pagination' => ['total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)]];
}

function get_order(int $id, ?int $userId = null): ?array {
    $sql    = "SELECT * FROM orders WHERE id = ?";
    $params = [$id];
    if ($userId) { $sql .= " AND user_id = ?"; $params[] = $userId; }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $order = $stmt->fetch();
    if (!$order) return null;

    $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll();

    $stmt = db()->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$order['id']]);
    $order['payment'] = $stmt->fetch() ?: null;

    $stmt = db()->prepare("SELECT * FROM shipments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$order['id']]);
    $order['shipment'] = $stmt->fetch() ?: null;

    if ($order['shipping_address_id']) {
        $stmt = db()->prepare("SELECT * FROM addresses WHERE id = ?");
        $stmt->execute([$order['shipping_address_id']]);
        $order['shipping_address'] = $stmt->fetch() ?: null;
    }

    return $order;
}

// ─── Formatting ───────────────────────────────────────────────────
function money(float $amount): string {
    return '$' . number_format($amount, 2);
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Render a product image or fall back to a category emoji.
 * Returns ready-to-echo HTML — either an <img> tag or an emoji character.
 *
 * @param array  $product  Product row (must have 'images' already decoded to array)
 * @param int    $index    Which image to use (0 = primary)
 * @param string $style    Extra inline CSS on the <img> tag
 */
function product_thumb(array $product, int $index = 0, string $style = 'width:100%;height:100%;object-fit:cover;display:block'): string {
    $images = $product['images'] ?? [];
    if (!empty($images[$index])) {
        $src = '/' . ltrim($images[$index], '/');
        $alt = e($product['name'] ?? '');
        return "<img src=\"{$src}\" alt=\"{$alt}\" style=\"{$style}\" loading=\"lazy\">";
    }
    static $icons = [
        'Smartphones'    => '📱',
        'Earbuds & Audio'=> '🎧',
        'Laptops'        => '💻',
        'Computer Parts' => '🖥️',
        'Accessories'    => '🔌',
        'Wearables'      => '⌚',
    ];
    return $icons[$product['category_name'] ?? ''] ?? '📦';
}

function status_badge(string $status): string {
    $map = [
        'pending'    => ['label' => 'Pending',    'class' => 'badge-orange'],
        'processing' => ['label' => 'Processing', 'class' => 'badge-blue'],
        'shipped'    => ['label' => 'Shipped',    'class' => 'badge-teal'],
        'delivered'  => ['label' => 'Delivered',  'class' => 'badge-green'],
        'cancelled'  => ['label' => 'Cancelled',  'class' => 'badge-red'],
        'refunded'   => ['label' => 'Refunded',   'class' => 'badge-purple'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-gray'];
    return "<span class=\"badge {$s['class']}\">{$s['label']}</span>";
}

function stars(float $rating): string {
    $full    = (int)$rating;
    $html    = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $full ? '⭐' : '☆';
    }
    return $html;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}
