<?php
$pageTitle = 'Track Order';
$extraCss  = ['track.css'];
require_once 'includes/header.php';

$orderNum = get('order');
$order    = null;
$error    = '';

if ($orderNum) {
    $stmt = db()->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([trim($orderNum)]);
    $row = $stmt->fetch();
    if ($row) {
        $order = get_order($row['id']);
    } else {
        $error = 'Order not found. Please check your order number.';
    }
}
$steps   = ['Order Placed','Processing','Shipped','Delivered'];
$stepMap = ['pending'=>0,'processing'=>1,'shipped'=>2,'delivered'=>3];
?>

<div class="track-hero page-hero"><h1>Track Your Order</h1><p>Enter your order number to see the latest status</p></div>
<div class="track-container">
    <form class="track-search" method="GET" action="/track.php">
        <div class="track-input">
            <i class="fas fa-search"></i>
            <input type="text" name="order" placeholder="Enter order number e.g. ORD-1745123456789" value="<?= e($orderNum) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Track Order</button>
    </form>

    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($order): ?>
    <div class="track-result">
        <div class="track-header">
            <div><h2>Order #<?= e($order['order_number']) ?></h2><p>Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?></p></div>
            <?= status_badge($order['status']) ?>
        </div>

        <?php if (!in_array($order['status'],['cancelled','refunded'])): ?>
        <div class="track-progress">
            <?php $current = $stepMap[$order['status']] ?? 0; ?>
            <?php foreach ($steps as $i => $step): ?>
            <div class="tp-step <?= $i<=$current?'done':'' ?> <?= $i===$current?'active':'' ?>">
                <div class="tp-icon">
                    <?php $stepIcons = ['fas fa-check-circle','fas fa-cog','fas fa-shipping-fast','fas fa-box-open']; ?>
                    <i class="<?= $i < $current ? 'fas fa-check' : $stepIcons[$i] ?>"></i>
                </div>
                <span><?= $step ?></span>
                <?php if ($i < count($steps)-1): ?><div class="tp-line <?= $i<$current?'done':'' ?>"></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($order['shipment']): ?>
        <div class="track-shipment card">
            <div class="card-header"><i class="fas fa-truck"></i> Shipment Details</div>
            <div class="ship-grid">
                <div><span>Carrier</span><strong><?= e($order['shipment']['carrier']) ?></strong></div>
                <div><span>Tracking No.</span><strong style="font-family:monospace;color:var(--accent-dark)"><?= e($order['shipment']['tracking_number']) ?></strong></div>
                <div><span>Status</span><strong><?= e(str_replace('_',' ',$order['shipment']['status'])) ?></strong></div>
                <?php if ($order['shipment']['estimated_delivery']): ?><div><span>Est. Delivery</span><strong><?= date('M j, Y', strtotime($order['shipment']['estimated_delivery'])) ?></strong></div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="fas fa-box"></i> Items in This Order</div>
            <?php foreach ($order['items'] as $item): ?>
            <div class="od-item">
                <div class="od-item-img">📦</div>
                <div class="od-item-info"><strong><?= e($item['product_name']) ?></strong><span>SKU: <?= e($item['product_sku']) ?> &nbsp;·&nbsp; Qty: <?= $item['quantity'] ?></span></div>
                <div class="od-item-total"><?= money($item['quantity']*$item['unit_price']) ?></div>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;padding-top:14px;border-top:1px solid var(--gray-200);font-size:15px">
                <span style="color:var(--gray-500)">Order Total</span>
                <strong style="font-family:var(--font-heading);font-size:18px"><?= money($order['total_amount']) ?></strong>
            </div>
        </div>

        <div style="display:flex;gap:12px">
            <a href="/catalog.php" class="btn btn-primary">Continue Shopping</a>
            <a href="/account/orders.php" class="btn btn-outline">All Orders</a>
        </div>
    </div>
    <?php elseif (!$error): ?>
    <div class="track-help">
        <h3>Need Help?</h3>
        <div class="help-cards">
            <div class="help-card"><i class="fas fa-envelope"></i><h4>Email Support</h4><p>hello@elevionsupply.com</p></div>
            <div class="help-card"><i class="fas fa-phone"></i><h4>Phone Support</h4><p>+1 (800) 555-TECH</p></div>
            <div class="help-card"><i class="fas fa-comments"></i><h4>Live Chat</h4><p>Mon–Fri, 9am–6pm PST</p></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
