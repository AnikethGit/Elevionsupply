<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_login();
$user = auth_user();
$msg  = '';

if (is_post()) {
    verify_csrf_form('/account/settings.php');

    $action = post('action', 'profile');

    if ($action === 'profile') {
        db()->prepare("UPDATE users SET first_name=?,last_name=?,phone=? WHERE id=?")
           ->execute([trim(post('first_name')), trim(post('last_name')), trim(post('phone')), $user['id']]);
        $msg = 'Profile updated successfully!';

    } elseif ($action === 'notifications') {
        $prefs = json_encode([
            'order_updates' => (bool)post('notif_order_updates'),
            'shipping'      => (bool)post('notif_shipping'),
            'promotions'    => (bool)post('notif_promotions'),
            'wholesale'     => (bool)post('notif_wholesale'),
        ]);
        db()->prepare("UPDATE users SET notification_prefs=? WHERE id=?")
           ->execute([$prefs, $user['id']]);
        $msg = 'Notification preferences saved!';
    }

    $user = auth_user(); // reload with updated values
}

$prefs     = $user['notification_prefs'];
$pageTitle = 'Account Settings';
$extraCss  = ['account.css'];
require_once '../includes/header.php';
?>
<div class="page-hero"><h1>Account Settings</h1><p>Manage your profile and preferences</p></div>
<div class="sidebar-layout">
    <aside>
        <nav class="sidebar-nav">
            <a href="/account/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="/account/orders.php"><i class="fas fa-box"></i> Orders</a>
            <a href="/account/addresses.php"><i class="fas fa-map-marker-alt"></i> Addresses</a>
            <a href="/account/settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>
    <main class="account-main">
        <!-- Profile -->
        <div class="card">
            <div class="card-header"><i class="fas fa-user"></i> Personal Information</div>
            <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>
            <form method="POST" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <div class="form-row">
                    <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?= e($user['first_name']) ?>"></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= e($user['last_name']) ?>"></div>
                </div>
                <div class="form-group"><label>Email Address <small>(cannot change)</small></label><input type="email" value="<?= e($user['email']) ?>" disabled style="background:var(--gray-100)"></div>
                <div class="form-group"><label>Phone Number</label><input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+1 (555) 000-0000"></div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <!-- Notifications -->
        <div class="card">
            <div class="card-header"><i class="fas fa-bell"></i> Notifications</div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="notifications">
                <div class="pref-list">
                    <?php foreach ([
                        ['order_updates', 'Order status updates',   'Get notified when your order status changes'],
                        ['shipping',      'Shipping notifications',  'Track your packages with email updates'],
                        ['promotions',    'Promotional emails',      'Deals, new products, and special offers'],
                        ['wholesale',     'Wholesale alerts',        'New bulk pricing and B2B offers'],
                    ] as [$key, $label, $sub]): ?>
                    <label class="pref-item">
                        <div class="pref-text"><span class="pref-label"><?= $label ?></span><span class="pref-sub"><?= $sub ?></span></div>
                        <label class="toggle">
                            <input type="checkbox" name="notif_<?= $key ?>" <?= !empty($prefs[$key]) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="text-align:right;margin-top:16px">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </div>
            </form>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header"><i class="fas fa-link"></i> Quick Links</div>
            <div class="quick-links">
                <a href="/account/addresses.php" class="quick-link"><i class="fas fa-map-marker-alt"></i><span>Manage Addresses</span><i class="fas fa-chevron-right"></i></a>
                <a href="/account/orders.php" class="quick-link"><i class="fas fa-box"></i><span>Order History</span><i class="fas fa-chevron-right"></i></a>
                <a href="/track.php" class="quick-link"><i class="fas fa-shipping-fast"></i><span>Track an Order</span><i class="fas fa-chevron-right"></i></a>
                <a href="/help/faq.php" class="quick-link"><i class="fas fa-question-circle"></i><span>Help &amp; FAQ</span><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="card danger-card">
            <div class="card-header" style="color:#c53030"><i class="fas fa-exclamation-triangle" style="color:#e53e3e"></i> Danger Zone</div>
            <p style="font-size:14px;color:var(--gray-500);margin-bottom:16px">Permanently delete your account and all associated data. This cannot be undone.</p>
            <form method="POST" action="/api/auth/delete.php" onsubmit="return confirm('Permanently delete your account? This cannot be undone.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">Delete Account</button>
            </form>
        </div>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
