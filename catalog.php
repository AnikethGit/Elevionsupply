<?php
$pageTitle = 'Shop Products';
$extraCss  = ['catalog.css'];
require_once 'includes/header.php';

$search   = get('search');
$category = get('category');
$sort     = get('sort', 'newest');
$minPrice = get('min_price');
$maxPrice = get('max_price');
$page     = max(1, (int)get('page', 1));

$filters = array_filter(compact('search', 'category', 'sort', 'minPrice', 'maxPrice'));
$result  = get_products($filters, $page, 12);
$products   = $result['data'];
$pagination = $result['pagination'];
$categories = get_categories();

function query(array $extra = [], array $remove = []): string {
    $params = $_GET;
    foreach ($remove as $k) unset($params[$k]);
    return '?' . http_build_query(array_merge($params, $extra));
}
?>

<div class="catalog-page">
    <!-- Filters Sidebar -->
    <aside class="filters-sidebar">
        <form method="GET" action="/catalog.php" id="filterForm">
            <div class="filter-header">
                <h3>Filters</h3>
                <a href="/catalog.php" class="clear-filters">Clear all</a>
            </div>

            <!-- Search -->
            <div class="filter-group">
                <label>Search</label>
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search products..." value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Categories -->
            <div class="filter-group">
                <label>Category</label>
                <div class="filter-options">
                    <a href="<?= query([], ['category', 'page']) ?>" class="filter-option <?= !$category ? 'active' : '' ?>">
                        All Products
                    </a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="<?= query(['category' => $cat['slug']], ['page']) ?>" class="filter-option <?= $category === $cat['slug'] ? 'active' : '' ?>">
                        <?= e($cat['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Price Range -->
            <div class="filter-group">
                <label>Price Range</label>
                <div class="price-inputs">
                    <input type="number" name="min_price" placeholder="Min $" value="<?= e($minPrice) ?>" min="0">
                    <span>–</span>
                    <input type="number" name="max_price" placeholder="Max $" value="<?= e($maxPrice) ?>" min="0">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="width:100%;margin-top:10px">Apply</button>
            </div>

            <input type="hidden" name="sort" value="<?= e($sort) ?>">
        </form>
    </aside>

    <!-- Main Content -->
    <div class="catalog-main">
        <!-- Top bar -->
        <div class="catalog-topbar">
            <p class="result-count">
                <?= number_format($pagination['total']) ?> product<?= $pagination['total'] !== 1 ? 's' : '' ?> found
                <?php if ($search): ?> for "<strong><?= e($search) ?></strong>"<?php endif; ?>
            </p>
            <div class="sort-select">
                <label>Sort:</label>
                <select onchange="window.location='<?= query() ?>&sort='+this.value">
                    <?php foreach (['newest' => 'Newest', 'price-asc' => 'Price: Low to High', 'price-desc' => 'Price: High to Low', 'rating' => 'Top Rated'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>No products found</h3>
            <p>Try adjusting your filters or search term.</p>
            <a href="/catalog.php" class="btn btn-primary">Clear Filters</a>
        </div>
        <?php else: ?>
        <div class="catalog-grid">
            <?php foreach ($products as $p): ?>
            <a href="/product.php?id=<?= $p['id'] ?>" class="catalog-card">
                <?php if ($p['badge']): ?>
                <span class="product-badge product-badge-<?= strtolower(e($p['badge'])) ?>"><?= e($p['badge']) ?></span>
                <?php endif; ?>
                <div class="catalog-img">
                    <?php $icons = ['Smartphones' => '📱','Earbuds & Audio' => '🎧','Laptops' => '💻','Computer Parts' => '🖥️','Accessories' => '🔌','Wearables' => '⌚']; ?>
                    <span><?= $icons[$p['category_name']] ?? '📦' ?></span>
                </div>
                <div class="catalog-info">
                    <div class="catalog-category"><?= e($p['category_name']) ?></div>
                    <h3 class="catalog-name"><?= e($p['name']) ?></h3>
                    <div class="catalog-rating">
                        <?= stars($p['rating']) ?>
                        <span>(<?= number_format($p['review_count']) ?>)</span>
                    </div>
                    <div class="catalog-footer">
                        <div class="catalog-price">
                            <?php if ($p['sale_price']): ?>
                            <span class="original-price"><?= money($p['price']) ?></span>
                            <?php endif; ?>
                            <span class="sale-price"><?= money($p['display_price']) ?></span>
                        </div>
                        <button class="btn btn-accent btn-sm" onclick="event.preventDefault();addToCart(<?= $p['id'] ?>)">
                            <i class="fas fa-cart-plus"></i>
                        </button>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['pages'] > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="<?= query(['page' => $page - 1]) ?>" class="page-btn">← Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($pagination['pages'], $page + 2); $i++): ?>
            <a href="<?= query(['page' => $i]) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pagination['pages']): ?>
            <a href="<?= query(['page' => $page + 1]) ?>" class="page-btn">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
