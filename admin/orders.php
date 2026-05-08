<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/admin.php';
require_admin();

$search = get('search');
$status = get('status', 'all');
$page   = max(1, (int)get('page', 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(o.order_number LIKE ? OR u.email LIKE ? OR u.first_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status !== 'all') { $where[] = 'o.status = ?'; $params[] = $status; }
$w = implode(' AND ', $where);

$total = (int)db()->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE $w")->execute($params) ? db()->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE $w")->execute($params) : 0;

$cnt = db()->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE $w");
$cnt->execute($params); $total = (int)$cnt->fetchColumn();

$stmt = db()->prepare("SELECT o.*, u.email, u.first_name, u.last_name
    FROM orders o LEFT JOIN users u ON u.id=o.user_id
    WHERE $w ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pages = (int)ceil($total / $limit);

// Quick stats
$stats = db()->query("SELECT
    COUNT(*) total,
    SUM(status='pending') pending,
    SUM(status='processing') processing,
    SUM(status='shipped') shipped,
    SUM(total_amount) revenue
    FROM orders")->fetch();

$pageTitle = 'Admin — Orders';
$extraCss  = ['account.css','admin.css'];
require_once '../includes/header.php';
?>
<div class="page-hero"><h1>Order Management</h1><p>Admin Panel</p></div>
<div class="admin-wrap">
    <div class="admin-topbar">
        <div style="display:flex;gap:8px;align-items:center">
            <a href="/admin/orders.php"   class="btn btn-outline btn-sm" style="<?= strpos($_SERVER['REQUEST_URI'],'orders')!==false?'background:var(--primary);color:#fff;border-color:var(--primary)':'' ?>"><i class="fas fa-receipt"></i> Orders</a>
            <a href="/admin/products.php" class="btn btn-outline btn-sm"><i class="fas fa-box-open"></i> Products</a>
        </div>
            <form method="GET" style="display:flex;gap:8px;align-items:center">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid var(--gray-200);border-radius:var(--radius-md);background:var(--white)">
                    <i class="fas fa-search" style="color:var(--gray-500);font-size:12px"></i>
                    <input type="text" name="search" placeholder="Order #, email, name…" value="<?= e($search) ?>" style="border:none;outline:none;font-size:13px;width:220px">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                <?php if ($search || $status !== 'all'): ?><a href="/admin/orders.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
            </form>
        </div>
        <a href="/admin/orders/create.php" class="btn btn-accent"><i class="fas fa-plus"></i> New Order</a>
    </div>

    <!-- Stats -->
    <div class="admin-stats">
        <div class="admin-stat"><div class="label">Total Orders</div><div class="value"><?= number_format($stats['total']) ?></div></div>
        <div class="admin-stat"><div class="label">Pending</div><div class="value"><?= (int)$stats['pending'] ?></div></div>
        <div class="admin-stat"><div class="label">Processing</div><div class="value"><?= (int)$stats['processing'] ?></div></div>
        <div class="admin-stat"><div class="label">Total Revenue</div><div class="value"><?= money((float)$stats['revenue']) ?></div></div>
    </div>

    <!-- Status tabs -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
        <?php foreach (['all','pending','processing','shipped','delivered','cancelled'] as $t): ?>
        <a href="?status=<?= $t ?><?= $search ? '&search='.urlencode($search) : '' ?>"
           class="filter-tab <?= $status===$t?'active':'' ?>"><?= ucfirst($t) ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Orders table -->
    <div class="admin-table-wrap">
        <?php if (empty($orders)): ?>
        <div class="empty-state"><div class="empty-icon">📦</div><h3>No orders found</h3></div>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr>
                <th>Order #</th><th>Customer</th><th>Date</th>
                <th>Status</th><th>Items</th><th style="text-align:right">Total</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong><?= e($o['order_number']) ?></strong></td>
                <td><?= $o['email'] ? e($o['first_name'].' '.$o['last_name']).'<br><small style="color:var(--gray-500)">'.e($o['email']).'</small>' : '<em style="color:var(--gray-500)">Guest</em>' ?></td>
                <td style="white-space:nowrap"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                <td><?= status_badge($o['status']) ?></td>
                <td>
                    <?php
                    $ic = db()->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=?");
                    $ic->execute([$o['id']]); echo $ic->fetchColumn();
                    ?> item(s)
                </td>
                <td style="text-align:right" class="num"><?= money($o['total_amount']) ?></td>
                <td style="white-space:nowrap">
                    <a href="/admin/orders/detail.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination" style="margin-top:16px">
        <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>" class="page-btn">← Prev</a><?php endif; ?>
        <span style="font-size:13px;color:var(--gray-500)">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>" class="page-btn">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
