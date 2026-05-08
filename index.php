<?php
$pageTitle       = 'ElevionSupply — Where Quality Meets Wholesale Value';
$pageDescription = 'Premium tech products at wholesale prices';
$extraCss        = ['home.css'];
require_once 'includes/header.php';

$featured = get_products(['is_featured' => 1], 1, 8);
$categories = get_categories();
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-inner">
        <div class="hero-eyebrow">New Season Arrivals</div>
        <h1>Where <span>Quality</span> Meets Wholesale Value</h1>
        <p class="hero-sub">Premium tech products at unbeatable wholesale prices. From flagship phones to pro audio gear — shop smarter, save bigger.</p>
        <div class="hero-ctas">
            <a href="/catalog.php" class="btn btn-primary btn-lg">Start Shopping</a>
            <a href="/catalog.php" class="btn btn-accent btn-lg">Browse Categories</a>
        </div>
    </div>
</section>

<!-- FEATURES BAR -->
<section class="features-bar">
    <div class="features-inner">
        <?php foreach ([
            ['icon' => 'fa-shipping-fast',  'title' => 'Free Shipping',    'sub' => 'On all orders over $150. Fast 2–5 day delivery.'],
            ['icon' => 'fa-shield-alt',     'title' => 'Secure Payments',  'sub' => '256-bit SSL encryption on all transactions.'],
            ['icon' => 'fa-undo-alt',       'title' => '30-Day Returns',   'sub' => 'No questions asked return policy.'],
            ['icon' => 'fa-headset',        'title' => '24/7 Support',     'sub' => 'Expert help whenever you need it.'],
        ] as $f): ?>
        <div class="feature-item">
            <div class="feature-icon"><i class="fas <?= $f['icon'] ?>"></i></div>
            <div>
                <div class="feature-title"><?= $f['title'] ?></div>
                <div class="feature-sub"><?= $f['sub'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- FEATURED PRODUCTS -->
<section class="featured-section">
    <div class="container">
        <div class="section-head">
            <h2>Featured Products</h2>
            <p>Handpicked bestsellers at wholesale prices</p>
        </div>
        <div class="product-grid">
            <?php foreach ($featured['data'] as $p): ?>
            <div class="product-card" data-id="<?= $p['id'] ?>">
                <?php if ($p['badge']): ?>
                <span class="product-badge product-badge-<?= strtolower(e($p['badge'])) ?>"><?= e($p['badge']) ?></span>
                <?php endif; ?>
                <div class="product-icon" style="<?= !empty($p['images'][0]) ? 'padding:0;overflow:hidden;border-radius:8px;' : '' ?>">
                    <?= product_thumb($p, 0, 'width:100%;height:100%;object-fit:cover;display:block') ?>
                </div>
                <div class="product-info">
                    <div class="product-name"><?= e($p['name']) ?></div>
                    <div class="product-category"><?= e($p['category_name']) ?></div>
                    <div class="product-rating">
                        <?= stars($p['rating']) ?>
                        <span class="review-count">(<?= number_format($p['review_count']) ?>)</span>
                    </div>
                    <div class="product-price"><?= money($p['display_price']) ?></div>
                    <button class="btn btn-accent btn-sm add-to-cart-btn" onclick="addToCart(<?= $p['id'] ?>)">
                        Add to Cart
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="view-all">
            <a href="/catalog.php" class="btn btn-outline">View All Products →</a>
        </div>
    </div>
</section>

<!-- CATEGORIES -->
<section class="categories-section">
    <div class="container">
        <div class="section-head">
            <h2>Shop by Category</h2>
            <p>Browse our curated collections</p>
        </div>
        <div class="category-grid">
            <?php
            $catIcons = ['smartphones' => '📱', 'laptops' => '💻', 'earbuds-audio' => '🎧', 'accessories' => '🔌', 'computer-parts' => '🖥️', 'wearables' => '⌚'];
            foreach ($categories as $cat):
            ?>
            <a href="/catalog.php?category=<?= e($cat['slug']) ?>" class="category-card">
                <div class="category-icon"><?= $catIcons[$cat['slug']] ?? '📦' ?></div>
                <span><?= e($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="cta-section">
    <div class="container">
        <div class="cta-inner">
            <div class="cta-content">
                <h2>Wholesale Pricing for B2B Customers</h2>
                <p>Join our wholesale program and enjoy bulk discounts on all products. Perfect for resellers, retailers, and corporate bulk purchases.</p>
                <a href="/register.php" class="btn btn-accent btn-lg">Apply for Wholesale Access</a>
            </div>
            <div class="cta-image">📦</div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-section">
    <div class="container">
        <div class="section-head">
            <h2>What Our Customers Say</h2>
        </div>
        <div class="testimonial-grid">
            <?php foreach ([
                ['name' => 'Alex Johnson',      'role' => 'Tech Reviewer',       'text' => 'ElevionSupply offers the best prices I\'ve found. Fast, reliable, professional.'],
                ['name' => 'Sarah Chen',         'role' => 'Retail Store Owner',  'text' => 'As a retailer, the wholesale pricing has transformed my margins. Highly recommended!'],
                ['name' => 'Marcus Rodriguez',   'role' => 'B2B Manager',         'text' => 'Professional, responsive, and competitive pricing. We\'ve found our new supplier.'],
            ] as $t): ?>
            <div class="testimonial-card">
                <div class="testimonial-rating">⭐⭐⭐⭐⭐</div>
                <p class="testimonial-text">"<?= e($t['text']) ?>"</p>
                <div class="testimonial-author">
                    <strong><?= e($t['name']) ?></strong>
                    <span><?= e($t['role']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ PREVIEW -->
<section class="faq-preview-section">
    <div class="container">
        <div class="section-head"><h2>Frequently Asked Questions</h2></div>
        <div class="faq-preview-grid">
            <?php foreach ([
                ['q' => 'Do you offer bulk discounts?',   'a' => 'Yes! We offer tiered wholesale pricing for bulk orders. Contact us for a custom quote.'],
                ['q' => 'How fast is shipping?',           'a' => 'Most orders ship within 24 hours. Standard delivery is 2–5 business days.'],
                ['q' => 'What\'s your return policy?',    'a' => '30-day hassle-free returns on all products in original condition.'],
                ['q' => 'Do you ship internationally?',   'a' => 'Yes, we ship to most countries. International orders may incur additional fees.'],
            ] as $item): ?>
            <div class="faq-preview-item">
                <h4><?= e($item['q']) ?></h4>
                <p><?= e($item['a']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:24px">
            <a href="/help/faq.php" class="btn btn-outline">View All FAQs →</a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
