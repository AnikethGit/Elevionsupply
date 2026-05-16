<?php
$pageTitle = 'Checkout';
$extraCss  = ['checkout.css'];
require_once 'includes/header.php';

$cartId = get_or_create_cart();
$items  = get_cart_items($cartId);
if (empty($items)) { redirect('/cart.php'); }

$subtotal = array_sum(array_column($items, 'subtotal'));
$shipping = $subtotal >= 150 ? 0 : 9.99;
$tax      = round($subtotal * 0.08875, 2);
$total    = $subtotal + $shipping + $tax;
$user     = auth_user();
?>

<div class="checkout-page">
    <div class="checkout-container">
        <h1 class="checkout-title"><i class="fas fa-lock"></i> Secure Checkout</h1>

        <div class="checkout-layout">
            <!-- Left: Forms -->
            <div class="checkout-forms">
                <!-- Progress -->
                <div class="checkout-steps">
                    <div class="checkout-step active" id="step1-tab">
                        <span class="step-num">1</span> Shipping
                    </div>
                    <div class="step-line"></div>
                    <div class="checkout-step" id="step2-tab">
                        <span class="step-num">2</span> Payment
                    </div>
                    <div class="step-line"></div>
                    <div class="checkout-step" id="step3-tab">
                        <span class="step-num">3</span> Review
                    </div>
                </div>

                <div id="errorBox" class="alert alert-error" style="display:none"></div>

                <!-- Step 1: Shipping -->
                <div id="step1" class="checkout-step-content">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-truck"></i> Shipping Address</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" id="ship_first" value="<?= e($user['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" id="ship_last" value="<?= e($user['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Street Address *</label>
                            <input type="text" id="ship_street" placeholder="123 Main St" required>
                        </div>
                        <div class="form-group">
                            <label>Apt, Suite, etc. (optional)</label>
                            <input type="text" id="ship_apt" placeholder="Apt 4B">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>City *</label>
                                <input type="text" id="ship_city" required>
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <input type="text" id="ship_state">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>ZIP / Postal Code *</label>
                                <input type="text" id="ship_zip" required>
                            </div>
                            <div class="form-group">
                                <label>Country</label>
                                <select id="ship_country">
                                    <option>United States</option>
                                    <option>Canada</option>
                                    <option>United Kingdom</option>
                                    <option>Australia</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email * <small style="color:var(--gray-500)">(used to track your order)</small></label>
                                <input type="email" id="ship_email" value="<?= e($user['email'] ?? '') ?>" placeholder="you@example.com" required>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" id="ship_phone" value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-lg" style="width:100%" onclick="goToStep(2)">Continue to Payment →</button>
                </div>

                <!-- Step 2: Payment -->
                <div id="step2" class="checkout-step-content" style="display:none">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-credit-card"></i> Payment Details</div>
                        <div class="form-group">
                            <label>Card Number *</label>
                            <input type="text" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCard(this)">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Expiry Date *</label>
                                <input type="text" id="card_expiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)">
                            </div>
                            <div class="form-group">
                                <label>CVV *</label>
                                <input type="text" id="card_cvv" placeholder="123" maxlength="4">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Cardholder Name *</label>
                            <input type="text" id="card_name" placeholder="Name as on card">
                        </div>
                    </div>
                    <div class="step-nav">
                        <button class="btn btn-outline" onclick="goToStep(1)">← Back</button>
                        <button class="btn btn-primary btn-lg" onclick="goToStep(3)">Review Order →</button>
                    </div>
                </div>

                <!-- Step 3: Review -->
                <div id="step3" class="checkout-step-content" style="display:none">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-check-circle"></i> Review Your Order</div>
                        <div id="reviewContent"></div>
                    </div>
                    <div class="step-nav">
                        <button class="btn btn-outline" onclick="goToStep(2)">← Back</button>
                        <button class="btn btn-primary btn-lg" id="placeOrderBtn" onclick="placeOrder()">
                            <i class="fas fa-lock"></i> Place Order — <?= money($total) ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: Order Summary -->
            <div class="order-summary">
                <h3>Order Summary</h3>
                <div class="summary-items">
                    <?php foreach ($items as $item): ?>
                    <div class="summary-item">
                        <span class="summary-item-name"><?= e($item['name']) ?> <span class="summary-qty">×<?= $item['quantity'] ?></span></span>
                        <span><?= money($item['subtotal']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-totals">
                    <div class="sum-row"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
                    <div class="sum-row"><span>Shipping</span><span><?= $shipping === 0 ? '<span class="free">FREE</span>' : money($shipping) ?></span></div>
                    <div class="sum-row"><span>Tax</span><span><?= money($tax) ?></span></div>
                </div>
                <div class="sum-total"><span>Total</span><strong><?= money($total) ?></strong></div>
                <div class="summary-badges">
                    <div><i class="fas fa-shield-alt"></i> SSL Encrypted</div>
                    <div><i class="fas fa-undo"></i> 30-Day Returns</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const orderData = { shipping: {}, payment: {} };

function goToStep(n) {
    if (n === 2 && !validateStep1()) return;
    if (n === 3 && !validateStep2()) return;
    if (n === 3) buildReview();
    [1,2,3].forEach(i => {
        document.getElementById(`step${i}`).style.display = i===n ? 'block' : 'none';
        document.getElementById(`step${i}-tab`).classList.toggle('active', i===n);
    });
    if (n > 1) collectStep1();
    if (n > 2) collectStep2();
}

function validateStep1() {
    const fields = ['ship_first','ship_last','ship_street','ship_city','ship_zip','ship_email'];
    for (const f of fields) {
        if (!document.getElementById(f).value.trim()) {
            showError('Please fill in all required shipping fields.'); return false;
        }
    }
    const email = document.getElementById('ship_email').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showError('Please enter a valid email address.'); return false;
    }
    hideError(); return true;
}

function validateStep2() {
    const card = document.getElementById('card_number').value.replace(/\s/g,'');
    if (card.length < 13) { showError('Please enter a valid card number.'); return false; }
    if (!document.getElementById('card_expiry').value.match(/^\d{2}\/\d{2}$/)) { showError('Please enter a valid expiry date (MM/YY).'); return false; }
    if (!document.getElementById('card_cvv').value.match(/^\d{3,4}$/)) { showError('Please enter a valid CVV.'); return false; }
    if (!document.getElementById('card_name').value.trim()) { showError('Please enter the cardholder name.'); return false; }
    hideError(); return true;
}

function collectStep1() {
    orderData.shipping = {
        first_name: document.getElementById('ship_first').value,
        last_name:  document.getElementById('ship_last').value,
        street:     document.getElementById('ship_street').value,
        apt:        document.getElementById('ship_apt').value,
        city:       document.getElementById('ship_city').value,
        state:      document.getElementById('ship_state').value,
        zip:        document.getElementById('ship_zip').value,
        country:    document.getElementById('ship_country').value,
        phone:      document.getElementById('ship_phone').value,
        email:      document.getElementById('ship_email').value,
    };
}

function collectStep2() {
    orderData.payment = {
        card_number: document.getElementById('card_number').value.replace(/\s/g,''),
        expiry:      document.getElementById('card_expiry').value,
        cvv:         document.getElementById('card_cvv').value,
        name:        document.getElementById('card_name').value,
    };
}

function buildReview() {
    collectStep1(); collectStep2();
    const s = orderData.shipping;
    document.getElementById('reviewContent').innerHTML = `
        <div class="review-section">
            <div class="review-label">Shipping To</div>
            <p>${s.first_name} ${s.last_name}<br>${s.street}${s.apt?' '+s.apt:''}<br>${s.city}, ${s.state} ${s.zip}<br>${s.country}<br>${s.email}</p>
        </div>
        <div class="review-section">
            <div class="review-label">Payment</div>
            <p><i class="fas fa-credit-card"></i> Card ending in ${orderData.payment.card_number.slice(-4)}</p>
        </div>
    `;
}

async function placeOrder() {
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    try {
        // apiFetch (defined in main.js) automatically attaches the X-CSRF-Token header
        const res  = await apiFetch('/api/checkout/process.php', {
            ...orderData.payment,
            card_number: orderData.payment.card_number,
            shipping:    orderData.shipping,
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = `/order-success.php?order=${data.order_number}&total=${data.total}`;
        } else {
            showError(data.message || 'Payment failed. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> Place Order';
        }
    } catch {
        showError('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Place Order';
    }
}

function showError(msg) { const b = document.getElementById('errorBox'); b.textContent = msg; b.style.display = 'flex'; window.scrollTo({top:0,behavior:'smooth'}); }
function hideError()    { document.getElementById('errorBox').style.display = 'none'; }
function formatCard(el) { el.value = el.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim(); }
function formatExpiry(el) { let v = el.value.replace(/\D/g,''); if (v.length>=2) v = v.slice(0,2)+'/'+v.slice(2); el.value = v; }
</script>
<?php require_once 'includes/footer.php'; ?>
