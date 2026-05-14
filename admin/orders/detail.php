<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin.php';
require_admin();

$id    = (int)get('id');
$order = $id ? get_order($id) : null;
if (!$order) redirect('/admin/orders.php');

$msg = '';
if (is_post()) {
    verify_csrf_form('/admin/orders/detail.php?id='.$id);
    $newStatus = post('status');
    $allowed   = ['pending','processing','shipped','delivered','cancelled','refunded'];
    if (in_array($newStatus, $allowed)) {
        db()->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$newStatus, $id]);
        $order = get_order($id);
        $msg   = 'Status updated to ' . ucfirst($newStatus) . '.';
    }
}

$pageTitle = 'Order #' . $order['order_number'];
$extraCss  = ['account.css','order-detail.css','admin.css'];
require_once '../../includes/header.php';

$steps   = ['Order Placed','Processing','Shipped','Delivered'];
$stepMap = ['pending'=>0,'processing'=>1,'shipped'=>2,'delivered'=>3];
$current = $stepMap[$order['status']] ?? 0;
?>
<div class="page-hero">
    <h1>Order #<?= e($order['order_number']) ?></h1>
    <p>Admin — placed <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
</div>
<div class="order-detail-wrap">
    <a href="/admin/orders.php" class="back-link"><i class="fas fa-arrow-left"></i> All Orders</a>

    <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <div class="order-detail-layout">
        <div class="od-left">
            <!-- Status tracker -->
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
            <div class="cancelled-box"><i class="fas fa-times-circle"></i><div><h4>Order <?= ucfirst($order['status']) ?></h4></div></div>
            <?php endif; ?>

            <!-- Admin: Update Status -->
            <div class="card">
                <div class="card-header"><i class="fas fa-edit"></i> Update Status</div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div style="display:flex;gap:10px;align-items:flex-end">
                        <div class="form-group" style="flex:1">
                            <label>Order Status</label>
                            <select name="status">
                                <?php foreach (['pending','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
                                <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>

            <!-- Items -->
            <div class="card">
                <div class="card-header"><i class="fas fa-box"></i> Items Ordered</div>
                <?php foreach ($order['items'] as $item): ?>
                <div class="od-item">
                    <div class="od-item-img">📦</div>
                    <div class="od-item-info">
                        <strong><?= e($item['product_name']) ?></strong>
                        <span>SKU: <?= e($item['product_sku']) ?></span>
                        <span>Qty: <?= $item['quantity'] ?> × <?= money($item['unit_price']) ?></span>
                    </div>
                    <div class="od-item-total"><?= money($item['quantity'] * $item['unit_price']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="od-right">
            <!-- Invoice download (admin) -->
            <div class="card" style="text-align:center">
                <div class="card-header" style="justify-content:center"><i class="fas fa-file-pdf" style="color:#e53e3e"></i> Invoice</div>
                <a href="/api/invoice/download.php?order_id=<?= $order['id'] ?>"
                   class="btn btn-invoice" style="width:100%" target="_blank">
                    <i class="fas fa-download"></i> Download PDF Invoice
                </a>
            </div>

            <!-- Order summary -->
            <div class="card">
                <div class="card-header"><i class="fas fa-receipt"></i> Order Summary</div>
                <div class="od-summary">
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
                    <?php if ($order['payment']['transaction_id']): ?>
                    <div class="od-row"><span>Txn ID</span><span class="mono"><?= e($order['payment']['transaction_id']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Shipping address -->
            <?php if ($order['shipping_address']): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-map-marker-alt"></i> Ship To</div>
                <address class="od-address">
                    <strong><?= e($order['shipping_address']['first_name']) ?> <?= e($order['shipping_address']['last_name']) ?></strong>
                    <p><?= e($order['shipping_address']['street_address']) ?></p>
                    <p><?= e($order['shipping_address']['city']) ?>, <?= e($order['shipping_address']['state_province']) ?> <?= e($order['shipping_address']['postal_code']) ?></p>
                    <p><?= e($order['shipping_address']['country']) ?></p>
                </address>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
