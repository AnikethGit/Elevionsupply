// ─── Mobile Menu ──────────────────────────────────────────────────
const menuToggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');
if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
        const icon = menuToggle.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    });
}

// ─── Notifications ────────────────────────────────────────────────
function showNotification(message, type = 'success') {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const el = document.createElement('div');
    el.className = `notification notification-${type}`;
    el.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    el.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 9999;
        padding: 14px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
        display: flex; align-items: center; gap: 10px;
        box-shadow: 0 4px 24px rgba(0,0,0,.15);
        animation: slideIn .3s ease;
        background: ${type === 'success' ? '#276749' : '#c53030'}; color: #fff;
        max-width: 340px;
    `;
    document.body.appendChild(el);
    setTimeout(() => el.style.animation = 'slideOut .3s ease forwards', 2700);
    setTimeout(() => el.remove(), 3000);
}

// ─── Cart AJAX ────────────────────────────────────────────────────
async function addToCart(productId, quantity = 1) {
    try {
        const res  = await fetch('/api/cart/add.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ product_id: productId, quantity })
        });
        const data = await res.json();
        if (data.success) {
            updateCartUI(data.cart_count, data.cart_total);
            showNotification('Item added to cart!');
        } else {
            showNotification(data.message || 'Failed to add item', 'error');
        }
    } catch {
        showNotification('Network error. Please try again.', 'error');
    }
}

async function removeFromCart(itemId) {
    try {
        const res  = await fetch('/api/cart/remove.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ item_id: itemId })
        });
        const data = await res.json();
        if (data.success) {
            updateCartUI(data.cart_count, data.cart_total);
            const row = document.querySelector(`[data-item-id="${itemId}"]`);
            if (row) row.remove();
            if (typeof refreshCartTotals === 'function') refreshCartTotals(data);
            showNotification('Item removed from cart');
        }
    } catch {
        showNotification('Network error.', 'error');
    }
}

async function updateCartQty(itemId, quantity) {
    if (quantity < 1) { removeFromCart(itemId); return; }
    try {
        const res  = await fetch('/api/cart/update.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ item_id: itemId, quantity })
        });
        const data = await res.json();
        if (data.success) {
            updateCartUI(data.cart_count, data.cart_total);
            if (typeof refreshCartTotals === 'function') refreshCartTotals(data);
        }
    } catch {
        showNotification('Network error.', 'error');
    }
}

function updateCartUI(count, total) {
    const badge = document.querySelector('.cart-badge');
    const totalEl = document.querySelector('.cart-total');
    if (totalEl) totalEl.textContent = '$' + parseFloat(total).toFixed(2);
    if (count > 0) {
        if (badge) { badge.textContent = count; badge.style.display = 'flex'; }
        else {
            const cartBtn = document.querySelector('.cart-btn');
            if (cartBtn) {
                const b = document.createElement('span');
                b.className = 'cart-badge';
                b.textContent = count;
                cartBtn.appendChild(b);
            }
        }
    } else if (badge) {
        badge.remove();
    }
}

// ─── FAQ Accordion ────────────────────────────────────────────────
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.faq-item');
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
    });
});

// ─── Animation keyframes (injected) ──────────────────────────────
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn  { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
`;
document.head.appendChild(style);
