<?php
$pageTitle = 'Order Placed!';
require_once 'includes/header.php';
$orderNum = e(get('order'));
$total    = money((float)get('total', 0));
?>
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:60px 24px">
    <div style="text-align:center;max-width:520px">
        <div style="font-size:80px;margin-bottom:20px">🎉</div>
        <h1 style="font-size:32px;color:var(--primary);margin-bottom:10px">Order Placed!</h1>
        <p style="font-size:15px;color:var(--gray-500);margin-bottom:24px">Thank you for your order. We'll start processing it right away.</p>
        <div style="background:var(--gray-100);border-radius:var(--radius-lg);padding:24px;margin-bottom:28px">
            <div style="font-size:13px;color:var(--gray-500);margin-bottom:4px">Order Number</div>
            <div style="font-family:var(--font-heading);font-size:22px;font-weight:800;color:var(--primary)"><?= $orderNum ?></div>
            <div style="margin-top:12px;font-size:15px;color:var(--gray-600)">Total: <strong style="color:var(--primary)"><?= $total ?></strong></div>
        </div>
        <div style="display:flex;gap:12px;justify-content:center">
            <a href="/track.php?order=<?= $orderNum ?>" class="btn btn-primary"><i class="fas fa-shipping-fast"></i> Track Order</a>
            <a href="/catalog.php" class="btn btn-outline">Continue Shopping</a>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
