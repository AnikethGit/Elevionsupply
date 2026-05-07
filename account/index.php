<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_login();
$user    = auth_user();
$orders  = get_orders($user['id'], [], 1);
$recent  = array_slice($orders['data'], 0, 3);
$pageTitle = 'My Account';
$extraCss  = ['account.css'];
require_once '../includes/header.php';
?>
<div class="page-hero"><h1>My Account</h1><p>Welcome back, <?= e($user['first_name']) ?>!</p></div>
<div class="sidebar-layout">
    <aside>
        <nav class="sidebar-nav">
            <a href="/account/index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="/account/orders.php"><i class="fas fa-box"></i> Orders</a>
            <a href="/account/addresses.php"><i class="fas fa-map-marker-alt"></i> Addresses</a>
            <a href="/account/settings.php"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>
    <main class="account-main">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-box"></i></div><div class="stat-val"><?= $orders['pagination']['total'] ?></div><div class="stat-label">Total Orders</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-shipping-fast"></i></div><div class="stat-val"><?= count(array_filter($orders['data'], fn($o)=>$o['status']==='shipped')) ?></div><div class="stat-label">Shipped</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-val"><?= count(array_filter($orders['data'], fn($o)=>$o['status']==='delivered')) ?></div><div class="stat-label">Delivered</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-shopping-cart"></i></div><div class="stat-val"><?= money(array_sum(array_column($orders['data'], 'total_amount'))) ?></div><div class="stat-label">Total Spent</div></div>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header"><i class="fas fa-box"></i> Recent Orders
                <a href="/account/orders.php" class="view-all-link">View all →</a>
            </div>
            <?php if (empty($recent)): ?>
            <p style="color:var(--gray-500);font-size:14px">No orders yet. <a href="/catalog.php">Start shopping!</a></p>
            <?php else: foreach ($recent as $o): ?>
            <div class="order-row">
                <div>
                    <div class="order-num">Order #<?= e($o['order_number']) ?></div>
                    <div class="order-date"><?= date('M j, Y', strtotime($o['created_at'])) ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:16px">
                    <?= status_badge($o['status']) ?>
                    <span class="order-total"><?= money($o['total_amount']) ?></span>
                    <a href="/orders/detail.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">View</a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header"><i class="fas fa-link"></i> Quick Links</div>
            <div class="quick-links">
                <a href="/account/addresses.php" class="quick-link"><i class="fas fa-map-marker-alt"></i><span>Manage Addresses</span><i class="fas fa-chevron-right"></i></a>
                <a href="/track.php" class="quick-link"><i class="fas fa-shipping-fast"></i><span>Track an Order</span><i class="fas fa-chevron-right"></i></a>
                <a href="/help/faq.php" class="quick-link"><i class="fas fa-question-circle"></i><span>Help &amp; FAQ</span><i class="fas fa-chevron-right"></i></a>
                <a href="/catalog.php" class="quick-link"><i class="fas fa-shopping-bag"></i><span>Continue Shopping</span><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
