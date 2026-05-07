<?php
$pageTitle = 'Shopping Cart';
$extraCss  = ['cart.css'];
require_once 'includes/header.php';

$cartId = get_or_create_cart();
$items  = get_cart_items($cartId);
$subtotal = array_sum(array_column($items, 'subtotal'));
$shipping = $subtotal >= 150 ? 0 : ($subtotal > 0 ? 9.99 : 0);
$tax      = $subtotal > 0 ? round($subtotal * 0.08875, 2) : 0;
$total    = $subtotal + $shipping + $tax;
?>

<div class="cart-page">
    <div class="cart-container">
        <div class="cart-header">
            <h1>Shopping Cart</h1>
            <p><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> in your cart</p>
        </div>

        <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Browse our catalog and add some products.</p>
            <a href="/catalog.php" class="btn btn-primary">Start Shopping</a>
        </div>
        <?php else: ?>
        <div class="cart-layout">
            <!-- Items -->
            <div class="cart-items" id="cartItems">
                <?php foreach ($items as $item): ?>
                <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                    <div class="item-img">📦</div>
                    <div class="item-details">
                        <a href="/product.php?id=<?= $item['product_id'] ?>" class="item-name"><?= e($item['name']) ?></a>
                        <div class="item-sku">SKU: <?= e($item['sku']) ?></div>
                        <div class="item-price"><?= money($item['unit_price']) ?> each</div>
                    </div>
                    <div class="item-qty">
                        <button class="qty-btn" onclick="changeItemQty(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)">−</button>
                        <span class="qty-val" id="qty-<?= $item['id'] ?>"><?= $item['quantity'] ?></span>
                        <button class="qty-btn" onclick="changeItemQty(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
                    </div>
                    <div class="item-subtotal" id="sub-<?= $item['id'] ?>"><?= money($item['subtotal']) ?></div>
                    <button class="remove-btn" onclick="removeFromCart(<?= $item['id'] ?>)" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <div class="cart-summary">
                <h3>Order Summary</h3>
                <div class="summary-rows">
                    <div class="summary-row"><span>Subtotal</span><span id="summarySubtotal"><?= money($subtotal) ?></span></div>
                    <div class="summary-row"><span>Shipping</span><span id="summaryShipping"><?= $shipping === 0 ? '<span class="free">FREE</span>' : money($shipping) ?></span></div>
                    <div class="summary-row"><span>Tax (8.875%)</span><span id="summaryTax"><?= money($tax) ?></span></div>
                    <?php if ($subtotal < 150 && $subtotal > 0): ?>
                    <div class="free-ship-note"><i class="fas fa-shipping-fast"></i> Add <?= money(150 - $subtotal) ?> more for free shipping!</div>
                    <?php endif; ?>
                </div>
                <div class="summary-total">
                    <span>Total</span>
                    <strong id="summaryTotal"><?= money($total) ?></strong>
                </div>
                <a href="/checkout.php" class="btn btn-primary btn-lg" style="width:100%;text-align:center;margin-top:16px">
                    <i class="fas fa-lock"></i> Proceed to Checkout
                </a>
                <a href="/catalog.php" class="btn btn-outline" style="width:100%;text-align:center;margin-top:10px">Continue Shopping</a>
                <div class="secure-note"><i class="fas fa-shield-alt"></i> Secure checkout with SSL encryption</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function changeItemQty(itemId, qty) {
    await updateCartQty(itemId, qty);
    if (qty < 1) {
        document.querySelector(`[data-item-id="${itemId}"]`)?.remove();
    } else {
        const res  = await fetch('/api/cart/update.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({item_id:itemId,quantity:qty}) });
        const data = await res.json();
        if (data.success) {
            document.getElementById(`qty-${itemId}`).textContent = qty;
            refreshCartTotals(data);
        }
    }
}
function refreshCartTotals(data) {
    if (!data.items) return;
    const subtotal = data.items.reduce((s,i)=>s+(i.unit_price*i.quantity),0);
    const shipping = subtotal>=150?0:(subtotal>0?9.99:0);
    const tax      = subtotal>0?subtotal*0.08875:0;
    const total    = subtotal+shipping+tax;
    const fmt = v=>'$'+v.toFixed(2);
    const el = id=>document.getElementById(id);
    if(el('summarySubtotal')) el('summarySubtotal').textContent=fmt(subtotal);
    if(el('summaryShipping')) el('summaryShipping').innerHTML=shipping===0?'<span class="free">FREE</span>':fmt(shipping);
    if(el('summaryTax'))      el('summaryTax').textContent=fmt(tax);
    if(el('summaryTotal'))    el('summaryTotal').textContent=fmt(total);
    data.items.forEach(item=>{
        const s=document.getElementById(`sub-${item.id}`);
        const q=document.getElementById(`qty-${item.id}`);
        if(s) s.textContent=fmt(item.unit_price*item.quantity);
        if(q) q.textContent=item.quantity;
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>
