<?php
$pageTitle = 'Track Order';
$extraCss  = ['track.css'];
require_once 'includes/header.php';

$orderNum = trim(get('order'));
$email    = strtolower(trim(get('email')));
$order    = null;
$error    = '';

if ($orderNum) {
    if (!$email) {
        $error = 'Please enter the email address associated with your order.';
    } else {
        $stmt = db()->prepare("
            SELECT o.*
            FROM orders o
            LEFT JOIN users u    ON u.id  = o.user_id
            LEFT JOIN addresses a ON a.id = o.shipping_address_id
            WHERE o.order_number = ?
              AND (
                  LOWER(COALESCE(u.email, ''))  = ?
               OR LOWER(COALESCE(a.email, '')) = ?
              )
            LIMIT 1
        ");
        $stmt->execute([trim($orderNum), $email, $email]);
        $row = $stmt->fetch();

        if ($row) {
            $order = get_order($row['id']);
        } else {
            $error = 'No order found matching that order number and email address.';
        }
    }
}

$steps   = ['Order Placed','Processing','Shipped','Delivered'];
$stepMap = ['pending'=>0,'processing'=>1,'shipped'=>2,'delivered'=>3];
?>

<div class="track-hero page-hero"><h1>Track Your Order</h1><p>Enter your order number and email to see the latest status</p></div>
<div class="track-container">
    <form class="track-search" method="GET" action="/track" style="flex-wrap:wrap;gap:10px">
        <div class="track-input" style="flex:1;min-width:200px">
            <i class="fas fa-hashtag"></i>
            <input type="text" name="order" placeholder="Order number e.g. ORD-17456…" value="<?= e($orderNum) ?>" required>
        </div>
        <div class="track-input" style="flex:1;min-width:200px">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" placeholder="Email address on order" value="<?= e(get('email')) ?>" required>
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
                <?php if ($order['shipment']['carrier']): ?>
                <div><span>Carrier</span><strong><?= e($order['shipment']['carrier']) ?></strong></div>
                <?php endif; ?>
                <?php if ($order['shipment']['tracking_number']): ?>
                <div><span>Tracking No.</span><strong style="font-family:monospace;color:var(--accent-dark)"><?= e($order['shipment']['tracking_number']) ?></strong></div>
                <?php endif; ?>
                <?php if ($order['shipment']['estimated_delivery']): ?>
                <div><span>Est. Delivery</span><strong><?= date('M j, Y', strtotime($order['shipment']['estimated_delivery'])) ?></strong></div>
                <?php endif; ?>
                <?php if ($order['shipment']['tracking_url']): ?>
                <div style="grid-column:1/-1">
                    <span>Track Link</span>
                    <strong><a href="<?= e($order['shipment']['tracking_url']) ?>" target="_blank" rel="noopener" style="color:var(--accent-dark);word-break:break-all"><?= e($order['shipment']['tracking_url']) ?></a></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="fas fa-box"></i> Items in This Order</div>
            <?php foreach ($order['items'] as $item): ?>
            <div class="od-item">
                <div class="od-item-img">
                    <?php
                    $pData = get_product((int)$item['product_id']);
                    echo $pData ? product_thumb($pData, 0, 'width:40px;height:40px;object-fit:cover;border-radius:6px') : '📦';
                    ?>
                </div>
                <div class="od-item-info"><strong><?= e($item['product_name']) ?></strong><span>SKU: <?= e($item['product_sku']) ?> &nbsp;·&nbsp; Qty: <?= $item['quantity'] ?></span></div>
                <div class="od-item-total"><?= money($item['quantity']*$item['unit_price']) ?></div>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;padding-top:14px;border-top:1px solid var(--gray-200);font-size:15px">
                <span style="color:var(--gray-500)">Order Total</span>
                <strong style="font-family:var(--font-heading);font-size:18px"><?= money($order['total_amount']) ?></strong>
            </div>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a href="/catalog.php" class="btn btn-primary">Continue Shopping</a>
            <a href="/account/orders.php" class="btn btn-outline">All Orders</a>
            <?php if (!in_array($order['status'], ['cancelled','refunded'])): ?>
            <a href="/api/invoice/download.php?order=<?= urlencode($order['order_number']) ?>"
               class="btn btn-invoice" target="_blank">
                <i class="fas fa-file-pdf"></i> Download Invoice
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (!$error): ?>
    <div class="track-help">
        <h3>Need Help?</h3>
        <div class="help-cards">
            <div class="help-card"><i class="fas fa-envelope"></i><h4>Email Support</h4><p>hello@elevionsupply.com</p></div>
            <div class="help-card"><i class="fas fa-phone"></i><h4>Phone Support</h4><p>+1 518 644 1943</p></div>
            <div class="help-card"><i class="fas fa-comments"></i><h4>Live Chat</h4><p>Mon–Fri, 9am–6pm PST</p></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
