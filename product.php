<?php
require_once 'includes/functions.php';
$id      = (int)get('id');
$product = $id ? get_product($id) : null;
if (!$product) { header('Location: /catalog.php'); exit; }

$pageTitle = $product['name'];
$extraCss  = ['product.css'];
require_once 'includes/header.php';

// Related products
$related = get_products(['category' => $product['category_slug']], 1, 4);
?>

<div class="product-page">
    <div class="product-container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/catalog.php">Catalog</a>
            <i class="fas fa-chevron-right"></i>
            <a href="/catalog.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
            <i class="fas fa-chevron-right"></i>
            <span><?= e($product['name']) ?></span>
        </nav>

        <div class="product-layout">
            <!-- Image -->
            <div class="product-gallery">
                <div class="main-image" id="mainImage">
                    <?php if (!empty($product['images'][0])): ?>
                        <img src="/<?= e(ltrim($product['images'][0],'/')) ?>"
                             alt="<?= e($product['name']) ?>"
                             style="width:100%;height:100%;object-fit:contain;border-radius:var(--radius-xl)"
                             id="mainImg">
                    <?php else: ?>
                        <?php $icons = ['Smartphones'=>'📱','Earbuds & Audio'=>'🎧','Laptops'=>'💻','Computer Parts'=>'🖥️','Accessories'=>'🔌','Wearables'=>'⌚']; ?>
                        <span class="product-emoji"><?= $icons[$product['category_name']] ?? '📦' ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($product['badge']): ?>
                <span class="gallery-badge product-badge-<?= strtolower(e($product['badge'])) ?>"><?= e($product['badge']) ?></span>
                <?php endif; ?>
                <?php if (count($product['images']) > 1): ?>
                <div class="thumb-strip">
                    <?php foreach ($product['images'] as $i => $img): ?>
                    <div class="thumb <?= $i===0?'active':'' ?>" onclick="switchImg(this,'<?= e('/'.ltrim($img,'/')) ?>')">
                        <img src="/<?= e(ltrim($img,'/')) ?>" alt="<?= e($product['name']) ?> image <?= $i+1 ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="product-details">
                <div class="product-category-tag"><?= e($product['category_name']) ?></div>
                <h1 class="product-title"><?= e($product['name']) ?></h1>

                <div class="product-meta">
                    <div class="product-stars">
                        <?= stars($product['rating']) ?>
                        <span class="rating-num"><?= $product['rating'] ?></span>
                        <span class="rating-reviews">(<?= number_format($product['review_count']) ?> reviews)</span>
                    </div>
                    <span class="sku-tag">SKU: <?= e($product['sku']) ?></span>
                </div>

                <div class="product-pricing">
                    <?php if ($product['sale_price']): ?>
                    <span class="was-price"><?= money($product['price']) ?></span>
                    <span class="now-price"><?= money($product['sale_price']) ?></span>
                    <span class="savings-tag">Save <?= money($product['price'] - $product['sale_price']) ?></span>
                    <?php else: ?>
                    <span class="now-price"><?= money($product['price']) ?></span>
                    <?php endif; ?>
                </div>

                <p class="product-description"><?= e($product['description'] ?? '') ?></p>

                <!-- Stock -->
                <div class="stock-status <?= $product['stock_quantity'] > 0 ? 'in-stock' : 'out-stock' ?>">
                    <i class="fas fa-<?= $product['stock_quantity'] > 0 ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= $product['stock_quantity'] > 0 ? "In Stock ({$product['stock_quantity']} available)" : 'Out of Stock' ?>
                </div>

                <!-- Add to Cart -->
                <?php if ($product['stock_quantity'] > 0): ?>
                <div class="add-to-cart-section">
                    <div class="qty-control">
                        <button class="qty-btn" onclick="changeQty(-1)">−</button>
                        <input type="number" id="qtyInput" value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                        <button class="qty-btn" onclick="changeQty(1)">+</button>
                    </div>
                    <button class="btn btn-primary btn-lg add-main-btn" onclick="addToCartWithQty(<?= $product['id'] ?>)">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </div>
                <?php else: ?>
                <button class="btn btn-outline btn-lg" disabled>Out of Stock</button>
                <?php endif; ?>

                <!-- Trust badges -->
                <div class="trust-badges">
                    <div class="trust-item"><i class="fas fa-shield-alt"></i> Secure Payment</div>
                    <div class="trust-item"><i class="fas fa-truck"></i> Free Shipping $150+</div>
                    <div class="trust-item"><i class="fas fa-undo"></i> 30-Day Returns</div>
                </div>
            </div>
        </div>

        <!-- Specifications -->
        <?php if (!empty($product['specifications'])): ?>
        <div class="specs-section">
            <h2>Specifications</h2>
            <div class="specs-grid">
                <?php foreach ($product['specifications'] as $key => $val): ?>
                <div class="spec-row">
                    <span class="spec-key"><?= e($key) ?></span>
                    <span class="spec-val"><?= e($val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Related Products -->
        <?php
        $relProducts = array_filter($related['data'], fn($p) => $p['id'] !== $product['id']);
        if (!empty($relProducts)):
        ?>
        <div class="related-section">
            <h2>Related Products</h2>
            <div class="related-grid">
                <?php foreach (array_slice($relProducts, 0, 3) as $r): ?>
                <a href="/product.php?id=<?= $r['id'] ?>" class="related-card">
                    <div class="related-img"><?= product_thumb($r, 0, 'width:40px;height:40px;object-fit:cover;border-radius:6px') ?></div>
                    <div class="related-info">
                        <h4><?= e($r['name']) ?></h4>
                        <span><?= money($r['display_price']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.thumb-strip { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.thumb { width:68px; height:68px; border-radius:var(--radius-md); border:2px solid var(--gray-200); overflow:hidden; cursor:pointer; transition:border-color var(--transition); flex-shrink:0; }
.thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.thumb:hover,.thumb.active { border-color:var(--accent); }
</style>
<script>
function switchImg(el, src) {
    const main = document.getElementById('mainImg');
    if (main) main.src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    const val   = Math.min(max, Math.max(1, parseInt(input.value) + delta));
    input.value = val;
}
function addToCartWithQty(productId) {
    const qty = parseInt(document.getElementById('qtyInput').value) || 1;
    const btn = document.querySelector('.add-main-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    addToCart(productId, qty).then(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Added!';
        setTimeout(() => btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart', 2000);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
