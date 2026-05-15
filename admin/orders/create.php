<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin.php';
require_admin();

// Product search AJAX
if (isset($_GET['product_search'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['product_search']) . '%';
    $stmt = db()->prepare("SELECT id, name, sku, COALESCE(sale_price,price) AS price, stock_quantity FROM products WHERE is_active=1 AND (name LIKE ? OR sku LIKE ?) LIMIT 8");
    $stmt->execute([$q, $q]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Customer search AJAX
if (isset($_GET['customer_search'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['customer_search']) . '%';
    $stmt = db()->prepare("SELECT id, email, first_name, last_name, phone FROM users WHERE is_active=1 AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ?) LIMIT 8");
    $stmt->execute([$q, $q, $q]);
    echo json_encode($stmt->fetchAll());
    exit;
}

$pageTitle = 'Create Order';
$extraCss  = ['account.css','admin.css'];
require_once '../../includes/header.php';
?>
<div class="page-hero"><h1>Create Manual Order</h1><p>Admin Panel</p></div>
<div class="admin-wrap">
    <a href="/admin/orders.php" style="display:inline-flex;align-items:center;gap:8px;color:var(--gray-500);font-size:13px;font-weight:600;margin-bottom:20px"><i class="fas fa-arrow-left"></i> Back to Orders</a>

    <div id="errorBox" class="alert alert-error" style="display:none;margin-bottom:16px"></div>

    <form id="createForm">
        <?= csrf_field() ?>
        <div class="create-layout">
            <!-- LEFT -->
            <div class="create-main">

                <!-- Customer -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-user"></i> Customer</div>
                    <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:16px">
                        <div class="form-group" style="flex:1">
                            <label>Search existing customer</label>
                            <input type="text" id="customerSearch" placeholder="Name or email…" autocomplete="off">
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearCustomer()">Guest Order</button>
                    </div>
                    <div id="customerResults" style="display:none;border:1px solid var(--gray-200);border-radius:var(--radius-md);margin-bottom:16px;overflow:hidden"></div>
                    <input type="hidden" name="customer_id" id="customerId">
                    <div id="customerPreview" style="display:none" class="alert alert-info"></div>
                    <div id="guestFields">
                        <div class="form-row">
                            <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="gFirst"></div>
                            <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="gLast"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Email</label><input type="email" name="email" id="gEmail"></div>
                            <div class="form-group"><label>Phone</label><input type="tel" name="phone" id="gPhone"></div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-map-marker-alt"></i> Shipping Address</div>
                    <div class="form-row">
                        <div class="form-group"><label>First Name *</label><input type="text" name="ship_first" required></div>
                        <div class="form-group"><label>Last Name *</label><input type="text" name="ship_last" required></div>
                    </div>
                    <div class="form-group"><label>Street Address *</label><input type="text" name="ship_street" required></div>
                    <div class="form-group"><label>Apt / Suite</label><input type="text" name="ship_apt"></div>
                    <div class="form-row">
                        <div class="form-group"><label>City *</label><input type="text" name="ship_city" required></div>
                        <div class="form-group"><label>State</label><input type="text" name="ship_state"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Postal Code *</label><input type="text" name="ship_zip" required></div>
                        <div class="form-group"><label>Country</label>
                            <select name="ship_country">
                                <option>United States</option><option>Canada</option>
                                <option>United Kingdom</option><option>Australia</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Phone</label><input type="tel" name="ship_phone" id="shipPhone" placeholder="+1 (555) 000-0000"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="ship_email" id="shipEmail" placeholder="customer@example.com"></div>
                    </div>
                </div>

                <!-- Products -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-box"></i> Products</div>
                    <div class="product-search-row">
                        <div class="form-group">
                            <label>Search product by name or SKU</label>
                            <input type="text" id="productSearch" placeholder="e.g. iPhone, APPLE-IP16…" autocomplete="off">
                        </div>
                    </div>
                    <div id="productResults" style="display:none;border:1px solid var(--gray-200);border-radius:var(--radius-md);margin:8px 0;overflow:hidden"></div>

                    <div id="itemsEmpty" class="items-empty">No products added yet.</div>
                    <table class="items-table" id="itemsTable" style="display:none">
                        <thead><tr><th>Product</th><th>SKU</th><th style="width:80px">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Subtotal</th><th></th></tr></thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                    <div id="itemsData"></div><!-- hidden inputs injected here -->
                </div>

                <!-- Notes -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-sticky-note"></i> Notes</div>
                    <div class="form-group">
                        <textarea name="notes" rows="3" placeholder="Internal notes (not visible to customer)…" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:var(--radius-md);font-family:var(--font-body);font-size:14px;resize:vertical"></textarea>
                    </div>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="create-side">
                <div class="card">
                    <div class="card-header"><i class="fas fa-receipt"></i> Order Details</div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label>Payment Method</label>
                        <select name="payment_method" id="paymentMethod" onchange="toggleCardField()">
                            <option value="credit_card">Credit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="form-group" id="cardLastFourWrap" style="margin-bottom:16px">
                        <label>Card Last 4 Digits <small style="color:var(--gray-500)">(optional)</small></label>
                        <input type="text" name="card_last_four" id="cardLastFour"
                               maxlength="4" pattern="\d{4}"
                               placeholder="e.g. 1234"
                               style="font-family:monospace;letter-spacing:3px">
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label>Payment Status</label>
                        <select name="payment_status">
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:20px">
                        <label>Order Status</label>
                        <select name="order_status">
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:20px">
                        <label>Tracking URL <small style="color:var(--gray-500)">(optional — printed on invoice)</small></label>
                        <input type="url" name="tracking_url" id="trackingUrl"
                               placeholder="https://track.carrier.com/ABC123">
                    </div>

                    <div class="order-total-box">
                        <div class="order-total-row"><span>Subtotal</span><span id="totSubtotal">£0.00</span></div>
                        <div class="order-total-row">
                            <span>Shipping ($)</span>
                            <input type="number" name="shipping_cost" id="shippingInput" min="0" step="0.01"
                                   value="0.00" placeholder="0.00"
                                   style="width:80px;padding:4px 8px;border:1px solid var(--gray-200);border-radius:6px;text-align:right;font-size:13px"
                                   oninput="updateTotals()">
                        </div>
                        <div class="order-total-row"><span>Tax (8.875%)</span><span id="totTax">£0.00</span></div>
                        <div class="order-total-row grand"><span>Total</span><span id="totTotal">£0.00</span></div>
                    </div>

                    <button type="button" onclick="submitOrder()" class="btn btn-primary" style="width:100%;margin-top:16px">
                        <i class="fas fa-check"></i> Create Order
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const csrf      = document.querySelector('meta[name="csrf-token"]').content;
let items = [];   // [{product_id, name, sku, quantity, unit_price}]

function toggleCardField() {
    const method = document.getElementById('paymentMethod').value;
    const wrap   = document.getElementById('cardLastFourWrap');
    wrap.style.display = (method === 'credit_card' || method === 'debit_card') ? 'block' : 'none';
}
toggleCardField(); // run on load

// ── Customer search ──────────────────────────────────────────────
let csTimer;
document.getElementById('customerSearch').addEventListener('input', function() {
    clearTimeout(csTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('customerResults').style.display='none'; return; }
    csTimer = setTimeout(async () => {
        const r = await fetch(`/admin/orders/create.php?customer_search=${encodeURIComponent(q)}`);
        const data = await r.json();
        renderCustomerResults(data);
    }, 250);
});

function renderCustomerResults(data) {
    const el = document.getElementById('customerResults');
    if (!data.length) { el.style.display='none'; return; }
    el.innerHTML = data.map(c => `
        <div onclick='selectCustomer(${JSON.stringify(c)})' style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--gray-200);font-size:13px"
             onmouseover="this.style.background='var(--gray-100)'" onmouseout="this.style.background=''">
            <strong>${c.first_name} ${c.last_name}</strong> — ${c.email}
        </div>`).join('');
    el.style.display = 'block';
}

function selectCustomer(c) {
    document.getElementById('customerId').value = c.id;
    document.getElementById('customerSearch').value = c.first_name + ' ' + c.last_name;
    document.getElementById('customerResults').style.display = 'none';
    document.getElementById('customerPreview').innerHTML =
        `<i class="fas fa-user-check"></i> <strong>${c.first_name} ${c.last_name}</strong> (${c.email})`;
    document.getElementById('customerPreview').style.display = 'flex';
    document.getElementById('guestFields').style.display = 'none';
    // Pre-fill shipping fields from customer
    document.querySelector('[name=ship_first]').value  = c.first_name;
    document.querySelector('[name=ship_last]').value   = c.last_name;
    if (document.getElementById('shipPhone')) document.getElementById('shipPhone').value = c.phone || '';
    if (document.getElementById('shipEmail')) document.getElementById('shipEmail').value = c.email || '';
}

function clearCustomer() {
    document.getElementById('customerId').value = '';
    document.getElementById('customerSearch').value = '';
    document.getElementById('customerPreview').style.display = 'none';
    document.getElementById('guestFields').style.display = 'block';
}

// ── Product search ───────────────────────────────────────────────
let psTimer;
document.getElementById('productSearch').addEventListener('input', function() {
    clearTimeout(psTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('productResults').style.display='none'; return; }
    psTimer = setTimeout(async () => {
        const r = await fetch(`/admin/orders/create.php?product_search=${encodeURIComponent(q)}`);
        const data = await r.json();
        renderProductResults(data);
    }, 250);
});

function renderProductResults(data) {
    const el = document.getElementById('productResults');
    if (!data.length) { el.style.display='none'; return; }
    el.innerHTML = data.map(p => `
        <div onclick='addProduct(${JSON.stringify(p)})' style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--gray-200);font-size:13px;display:flex;justify-content:space-between"
             onmouseover="this.style.background='var(--gray-100)'" onmouseout="this.style.background=''">
            <span><strong>${p.name}</strong> <span style="color:var(--gray-500)">(${p.sku})</span></span>
            <span style="font-weight:700;color:var(--primary)">$${parseFloat(p.price).toFixed(2)}</span>
        </div>`).join('');
    el.style.display = 'block';
}

function addProduct(p) {
    document.getElementById('productSearch').value = '';
    document.getElementById('productResults').style.display = 'none';
    const existing = items.find(i => i.product_id == p.id);
    if (existing) { existing.quantity++; }
    else { items.push({product_id:p.id, name:p.name, sku:p.sku, quantity:1, unit_price:parseFloat(p.price)}); }
    renderItems();
}

function removeItem(idx) { items.splice(idx, 1); renderItems(); }

function changeQty(idx, val) {
    const q = parseInt(val);
    if (q < 1) return;
    items[idx].quantity = q;
    renderItems();
}

function renderItems() {
    const tbody  = document.getElementById('itemsBody');
    const table  = document.getElementById('itemsTable');
    const empty  = document.getElementById('itemsEmpty');
    const hidden = document.getElementById('itemsData');

    if (!items.length) { table.style.display='none'; empty.style.display='block'; hidden.innerHTML=''; updateTotals(); return; }
    table.style.display = 'table';
    empty.style.display = 'none';

    tbody.innerHTML = items.map((it, i) => `
        <tr>
            <td>${it.name}</td>
            <td style="color:var(--gray-500)">${it.sku}</td>
            <td><input type="number" min="1" value="${it.quantity}" onchange="changeQty(${i},this.value)"
                style="width:60px;padding:4px 8px;border:1px solid var(--gray-200);border-radius:6px;text-align:center"></td>
            <td style="text-align:right">$${it.unit_price.toFixed(2)}</td>
            <td style="text-align:right;font-weight:700">$${(it.unit_price*it.quantity).toFixed(2)}</td>
            <td><button type="button" class="del-btn" onclick="removeItem(${i})"><i class="fas fa-times"></i></button></td>
        </tr>`).join('');

    hidden.innerHTML = items.map((it,i) => `
        <input type="hidden" name="items[${i}][product_id]"  value="${it.product_id}">
        <input type="hidden" name="items[${i}][quantity]"    value="${it.quantity}">
        <input type="hidden" name="items[${i}][unit_price]"  value="${it.unit_price}">`).join('');

    updateTotals();
}

function updateTotals() {
    const sub  = items.reduce((s,i) => s + i.unit_price * i.quantity, 0);
    const ship = parseFloat(document.getElementById('shippingInput')?.value || 0) || 0;
    const tax  = sub > 0 ? sub * 0.08875 : 0;
    const tot  = sub + ship + tax;
    const fmt  = v => '£' + v.toFixed(2);
    document.getElementById('totSubtotal').textContent = fmt(sub);
    document.getElementById('totTax').textContent      = fmt(tax);
    document.getElementById('totTotal').textContent    = fmt(tot);
}

async function submitOrder() {
    if (!items.length) { showError('Add at least one product.'); return; }
    const form = document.getElementById('createForm');
    const fd   = new FormData(form);
    const body = {};
    for (const [k,v] of fd.entries()) {
        if (k.includes('[')) {
            const m = k.match(/^(\w+)\[(\d+)\]\[(\w+)\]$/);
            if (m) { if(!body[m[1]]) body[m[1]]=[]; if(!body[m[1]][m[2]]) body[m[1]][m[2]]={}; body[m[1]][m[2]][m[3]]=v; continue; }
        }
        body[k] = v;
    }
    try {
        const res  = await fetch('/api/admin/orders/create.php', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) window.location.href = '/admin/orders/detail.php?id=' + data.order_id;
        else showError(data.message || 'Failed to create order.');
    } catch { showError('Network error.'); }
}

function showError(msg) {
    const b = document.getElementById('errorBox');
    b.textContent = msg; b.style.display = 'flex';
    window.scrollTo({top:0,behavior:'smooth'});
}
</script>
<?php require_once '../../includes/footer.php'; ?>
