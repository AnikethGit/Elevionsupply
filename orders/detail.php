<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_login();
$user  = auth_user();
$id    = (int)get('id');
$order = $id ? get_order($id, $user['id']) : null;
if (!$order) { redirect('/account/orders.php'); }
$pageTitle = 'Order #'.$order['order_number'];
$extraCss  = ['account.css','order-detail.css'];
require_once '../includes/header.php';

$steps   = ['Order Placed','Processing','Shipped','Delivered'];
$stepMap = ['pending'=>0,'processing'=>1,'shipped'=>2,'delivered'=>3];
$current = $stepMap[$order['status']] ?? 0;
?>
<div class="page-hero"><h1>Order #<?= e($order['order_number']) ?></h1><p>Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?></p></div>
<div class="order-detail-wrap">
    <a href="/account/orders.php" class="back-link"><i class="fas fa-arrow-left"></i> My Orders</a>
    <div class="order-detail-layout">
        <div class="od-left">
            <!-- Tracker -->
            <?php if (!in_array($order['status'],['cancelled','refunded'])): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-map-marker-alt"></i> Order Status</div>
                <div class="od-tracker">
                    <?php foreach ($steps as $i => $step): ?>
                    <div class="od-step <?= $i<=$current?'done':'' ?> <?= $i===$current?'active':'' ?>">
                        <div class="od-circle"><?= $i<$current?'<i class="fas fa-check"></i>':$i+1 ?></div>
                        <span><?= $step ?></span>
                        <?php if ($i < count($steps)-1): ?><div class="od-line <?= $i<$current?'done':'' ?>"></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="cancelled-box"><i class="fas fa-times-circle"></i><div><h4>Order <?= ucfirst($order['status']) ?></h4><p>This order has been <?= $order['status'] ?>.</p></div></div>
            <?php endif; ?>

            <!-- Shipment -->
            <?php if ($order['shipment']): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-truck"></i> Shipment Details</div>
                <div class="ship-grid">
                    <div><span>Carrier</span><strong><?= e($order['shipment']['carrier']) ?></strong></div>
                    <div><span>Tracking</span><strong class="mono"><?= e($order['shipment']['tracking_number']) ?></strong></div>
                    <div><span>Status</span><strong><?= e(str_replace('_',' ',$order['shipment']['status'])) ?></strong></div>
                    <?php if ($order['shipment']['estimated_delivery']): ?><div><span>Est. Delivery</span><strong><?= date('M j, Y', strtotime($order['shipment']['estimated_delivery'])) ?></strong></div><?php endif; ?>
                </div>
                <a href="/track.php?order=<?= e($order['order_number']) ?>" class="btn btn-primary btn-sm" style="margin-top:14px"><i class="fas fa-map-marker-alt"></i> Live Tracking</a>
            </div>
            <?php endif; ?>

            <!-- Items -->
            <div class="card">
                <div class="card-header"><i class="fas fa-box"></i> Items Ordered</div>
                <?php foreach ($order['items'] as $item): ?>
                <div class="od-item">
                    <div class="od-item-img">
                        <?php
                        $pData = get_product((int)$item['product_id']);
                        echo $pData ? product_thumb($pData, 0, 'width:40px;height:40px;object-fit:cover;border-radius:6px') : '📦';
                        ?>
                    </div>
                    <div class="od-item-info"><strong><?= e($item['product_name']) ?></strong><span>SKU: <?= e($item['product_sku']) ?></span><span>Qty: <?= $item['quantity'] ?> × <?= money($item['unit_price']) ?></span></div>
                    <div class="od-item-total"><?= money($item['quantity'] * $item['unit_price']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="od-right">
            <!-- Summary -->
            <div class="card">
                <div class="card-header"><i class="fas fa-receipt"></i> Order Summary</div>
                <div class="od-summary">
                    <?php $invNum = !empty($order['invoice_number']) ? $order['invoice_number'] : 'INV-'.str_pad($order['id'],5,'0',STR_PAD_LEFT); ?>
                    <div class="od-row"><span>Invoice #</span><span class="mono" style="color:var(--accent-dark)"><?= e($invNum) ?></span></div>
                    <div class="od-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                    <div class="od-row"><span>Tax</span><span><?= money($order['tax_amount']) ?></span></div>
                    <div class="od-row"><span>Shipping</span><span><?= (float)$order['shipping_cost']===0.0?'<span style="color:#276749;font-weight:700">FREE</span>':money($order['shipping_cost']) ?></span></div>
                </div>
                <div class="od-total"><span>Total</span><strong><?= money($order['total_amount']) ?></strong></div>
            </div>
            <!-- Payment -->
            <?php if ($order['payment']): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-credit-card"></i> Payment</div>
                <div class="od-summary">
                    <div class="od-row"><span>Method</span><span><?= e(ucwords(str_replace('_',' ',$order['payment_method']))) ?><?php $cl4 = $order['payment']['card_last_four'] ?? ''; if ($cl4 && in_array($order['payment_method'],['credit_card','debit_card'])): ?> <span class="mono" style="color:var(--gray-500)">**** <?= e($cl4) ?></span><?php endif; ?></span></div>
                    <div class="od-row"><span>Status</span><span style="color:#276749;font-weight:700"><?= e($order['payment_status']) ?></span></div>
                    <?php if ($order['payment']['transaction_id']): ?><div class="od-row"><span>Transaction</span><span class="mono"><?= e($order['payment']['transaction_id']) ?></span></div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <!-- Shipping Address -->
            <?php if ($order['shipping_address']): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-map-marker-alt"></i> Shipping To</div>
                <address class="od-address">
                    <strong><?= e($order['shipping_address']['first_name']) ?> <?= e($order['shipping_address']['last_name']) ?></strong>
                    <p><?= e($order['shipping_address']['street_address']) ?></p>
                    <p><?= e($order['shipping_address']['city']) ?>, <?= e($order['shipping_address']['state_province']) ?> <?= e($order['shipping_address']['postal_code']) ?></p>
                    <p><?= e($order['shipping_address']['country']) ?></p>
                </address>
            </div>
            <?php endif; ?>
            <div style="display:flex;flex-direction:column;gap:10px">
                <a href="/track.php?order=<?= e($order['order_number']) ?>" class="btn btn-primary"><i class="fas fa-shipping-fast"></i> Track Order</a>
                <?php if (in_array($order['status'],['pending','processing'])): ?>
                <form method="POST" action="/api/orders/cancel.php" onsubmit="return confirm('Cancel this order?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="redirect" value="/orders/detail.php?id=<?= $order['id'] ?>">
                    <button class="btn" style="width:100%;border:1px solid #e53e3e;color:#e53e3e;background:none">Cancel Order</button>
                </form>
                <?php endif; ?>
                <a href="/account/orders.php" class="btn btn-outline" style="text-align:center">All Orders</a>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
