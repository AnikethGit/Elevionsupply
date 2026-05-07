<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_login();
$user   = auth_user();
$filter = get('status', 'all');
$page   = max(1, (int)get('page', 1));
$result = get_orders($user['id'], $filter !== 'all' ? ['status' => $filter] : [], $page);
$orders = $result['data'];
$pag    = $result['pagination'];
$pageTitle = 'My Orders';
$extraCss  = ['account.css'];
require_once '../includes/header.php';
$tabs = ['all','pending','processing','shipped','delivered','cancelled'];
?>
<div class="page-hero"><h1>My Orders</h1><p>Track and manage all your purchases</p></div>
<div class="sidebar-layout">
    <aside>
        <nav class="sidebar-nav">
            <a href="/account/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="/account/orders.php" class="active"><i class="fas fa-box"></i> Orders</a>
            <a href="/account/addresses.php"><i class="fas fa-map-marker-alt"></i> Addresses</a>
            <a href="/account/settings.php"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>
    <main class="account-main">
        <div class="filter-tabs">
            <?php foreach ($tabs as $t): ?>
            <a href="?status=<?= $t ?>" class="filter-tab <?= $filter===$t?'active':'' ?>"><?= ucfirst($t) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
        <div class="empty-state"><div class="empty-icon">📦</div><h3>No orders yet</h3><p><?= $filter!=='all'?"No $filter orders found.":'You haven\'t placed any orders yet.' ?></p><a href="/catalog.php" class="btn btn-primary">Start Shopping</a></div>
        <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $o): ?>
            <div class="order-card">
                <div class="order-card-top">
                    <div><h3>Order #<?= e($o['order_number']) ?></h3><span class="order-date"><?= date('M j, Y', strtotime($o['created_at'])) ?></span></div>
                    <div style="display:flex;align-items:center;gap:16px"><?= status_badge($o['status']) ?><span class="order-total"><?= money($o['total_amount']) ?></span></div>
                </div>
                <?php if (!empty($o['items'])): ?>
                <div class="order-items-preview">
                    <?php foreach (array_slice($o['items'], 0, 3) as $item): ?>
                    <div class="preview-item"><span>📦</span><span class="preview-name"><?= e($item['product_name']) ?></span><span class="preview-qty">×<?= $item['quantity'] ?></span></div>
                    <?php endforeach; ?>
                    <?php if (count($o['items']) > 3): ?><span class="more-items">+<?= count($o['items'])-3 ?> more</span><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="order-card-actions">
                    <a href="/track.php?order=<?= e($o['order_number']) ?>" class="btn btn-primary btn-sm"><i class="fas fa-map-marker-alt"></i> Track</a>
                    <a href="/orders/detail.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">View Details</a>
                    <?php if (in_array($o['status'],['pending','processing'])): ?>
                    <form method="POST" action="/api/orders/cancel.php" style="display:inline" onsubmit="return confirm('Cancel this order?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="border:1px solid #e53e3e;color:#e53e3e;background:none">Cancel</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($pag['pages'] > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="?status=<?=$filter?>&page=<?=$page-1?>" class="page-btn">← Prev</a><?php endif; ?>
            <span style="font-size:13px;color:var(--gray-500)">Page <?=$page?> of <?=$pag['pages']?></span>
            <?php if ($page < $pag['pages']): ?><a href="?status=<?=$filter?>&page=<?=$page+1?>" class="page-btn">Next →</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
