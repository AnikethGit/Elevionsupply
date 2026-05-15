<?php
$pageTitle       = 'ElevionSupply — Where Quality Meets Wholesale Value';
$pageDescription = 'Premium tech products at wholesale prices';
$extraCss        = ['home.css'];
require_once 'includes/header.php';

$featured   = get_products(['is_featured' => 1], 1, 8);
$categories = get_categories();

// Slug → bento/prod bg class map
$bentoBg = ['smartphones'=>'bento-phones','earbuds-audio'=>'bento-audio','accessories'=>'bento-access','laptops'=>'bento-laptops','computer-parts'=>'bento-parts','wearables'=>'bento-wear'];
$prodBg  = ['smartphones'=>'prod-bg-smartphones','earbuds-audio'=>'prod-bg-earbuds-audio','laptops'=>'prod-bg-laptops','computer-parts'=>'prod-bg-computer-parts','accessories'=>'prod-bg-accessories','wearables'=>'prod-bg-wearables'];
$emoji   = ['smartphones'=>'📱','earbuds-audio'=>'🎧','laptops'=>'💻','computer-parts'=>'🖥️','accessories'=>'🔌','wearables'=>'⌚'];
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
            ['fa-shipping-fast', 'Free Shipping',   'On all orders over £150. Fast 2–5 day delivery.'],
            ['fa-shield-alt',    'Secure Payments',  '256-bit SSL encryption on all transactions.'],
            ['fa-undo-alt',      '30-Day Returns',   'No questions asked return policy.'],
            ['fa-headset',       '24/7 Support',     'Expert help whenever you need it.'],
        ] as [$icon,$title,$sub]): ?>
        <div class="feature-item reveal">
            <div class="feature-icon"><i class="fas <?= $icon ?>"></i></div>
            <div>
                <div class="feature-title"><?= $title ?></div>
                <div class="feature-sub"><?= $sub ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- FEATURED PRODUCTS -->
<section class="featured-section">
    <div class="container">
        <div class="trending-header reveal">
            <div>
                <div class="section-label">What's Hot</div>
                <h2 class="section-title">Trending <em>Right Now</em></h2>
                <p class="section-sub">Our best-selling products this season</p>
            </div>
            <a href="/catalog.php" class="view-all-link-underline">View all products →</a>
        </div>
        <div class="product-grid">
            <?php foreach ($featured['data'] as $p):
                $slug = $p['category_slug'] ?? 'default';
                $bg   = $prodBg[$slug] ?? 'prod-bg-default';
                $em   = $emoji[$slug]  ?? '📦';
                $badgeClass = $p['badge'] ? 'product-badge-'.strtolower($p['badge']) : '';
            ?>
            <a href="/product.php?id=<?= $p['id'] ?>" class="product-card reveal">
                <div class="product-img-wrap <?= !empty($p['images'][0]) ? '' : $bg ?>">
                    <?php if (!empty($p['images'][0])): ?>
                        <img src="/<?= e(ltrim($p['images'][0],'/')) ?>" alt="<?= e($p['name']) ?>">
                    <?php else: ?>
                        <div class="product-emoji-placeholder"><?= $em ?></div>
                    <?php endif; ?>
                    <?php if ($p['badge']): ?>
                    <span class="product-badge <?= $badgeClass ?>"><?= e($p['badge']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-cat"><?= e($p['category_name']) ?></div>
                    <div class="product-name"><?= e($p['name']) ?></div>
                    <div class="product-stars-row">
                        <?= str_repeat('★', (int)$p['rating']) ?><?= str_repeat('☆', 5-(int)$p['rating']) ?>
                        <span>(<?= number_format($p['review_count']) ?>)</span>
                    </div>
                    <div class="product-footer">
                        <div>
                            <?php if ($p['sale_price']): ?>
                            <div class="product-price-old"><?= money($p['price']) ?></div>
                            <?php endif; ?>
                            <div class="product-price"><?= money($p['display_price']) ?></div>
                        </div>
                        <button class="add-cart-btn" onclick="event.preventDefault();addToCart(<?= $p['id'] ?>)">+ Add</button>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CATEGORIES (bento) -->
<section class="categories-section" id="categories">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-label">Explore</div>
            <h2 class="section-title">Shop by Category</h2>
            <p class="section-sub">Find exactly what you're looking for</p>
        </div>
        <div class="bento-grid reveal">
            <?php
            // Show up to 5 categories in bento layout
            $bentoSlots = array_slice($categories, 0, 5);
            foreach ($bentoSlots as $cat):
                $bg = $bentoBg[$cat['slug']] ?? 'bento-phones';
                $em = $emoji[$cat['slug']]   ?? '📦';
            ?>
            <div class="bento-item">
                <div class="bento-bg <?= $bg ?>"></div>
                <div class="bento-emoji"><?= $em ?></div>
                <div class="bento-overlay"></div>
                <div class="bento-content">
                    <div class="bento-label"><?= e($cat['name']) ?></div>
                    <a href="/catalog.php?category=<?= e($cat['slug']) ?>" class="bento-btn">Shop Now</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA / PROMO BANNER -->
<section class="cta-section">
    <div class="cta-inner reveal">
        <div class="promo-tag">Wholesale Program</div>
        <div class="cta-content">
            <h2>Buy More, Save More</h2>
            <p>Join our wholesale program and unlock tiered pricing on bulk orders. Perfect for retailers, resellers, and businesses of all sizes.</p>
            <a href="/register.php" class="btn btn-accent btn-lg">Apply for Wholesale Access</a>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-section">
    <div class="container">
        <div class="section-head reveal">
            <div class="section-label">What People Say</div>
            <h2 class="section-title">Trusted by Customers</h2>
        </div>
        <div class="testimonial-grid">
            <?php foreach ([
                ['Alex Johnson',    'Tech Reviewer',      'ElevionSupply offers the best prices I\'ve found. Fast, reliable, professional.'],
                ['Sarah Chen',      'Retail Store Owner', 'As a retailer, the wholesale pricing has transformed my margins. Highly recommended!'],
                ['Marcus Rodriguez','B2B Manager',        'Professional, responsive, and competitive pricing. We\'ve found our new supplier.'],
            ] as [$name,$role,$text]): ?>
            <div class="testimonial-card reveal">
                <div class="testimonial-rating">★★★★★</div>
                <p class="testimonial-text">"<?= e($text) ?>"</p>
                <div class="testimonial-author">
                    <strong><?= e($name) ?></strong>
                    <span><?= e($role) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ PREVIEW -->
<section class="faq-preview-section">
    <div class="container">
        <div class="section-head reveal">
            <div class="section-label">Got Questions?</div>
            <h2 class="section-title">Frequently Asked</h2>
        </div>
        <div class="faq-preview-grid">
            <?php foreach ([
                ['Do you offer bulk discounts?',   'Yes! We offer tiered wholesale pricing for bulk orders. Contact us for a custom quote.'],
                ['How fast is shipping?',           'Most orders ship within 24 hours. Standard delivery is 2–5 business days.'],
                ['What\'s your return policy?',    '30-day hassle-free returns on all products in original condition.'],
                ['Do you ship internationally?',   'Yes, we ship to most countries. International orders may incur additional fees.'],
            ] as [$q,$a]): ?>
            <div class="faq-preview-item reveal">
                <h4><?= e($q) ?></h4>
                <p><?= e($a) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:24px">
            <a href="/help/faq.php" class="btn btn-outline">View All FAQs →</a>
        </div>
    </div>
</section>

<!-- FOOTER TRIANGLE TRANSITION -->
<div class="footer-chevron"></div>

<!-- CHAT FAB -->
<button class="chat-fab" title="Chat with us" onclick="window.location='/help/faq.php'">
    <i class="fas fa-comment-dots"></i>
</button>

<!-- SCROLL REVEAL JS -->
<script>
(function() {
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach((el, i) => {
        // Stagger siblings within the same parent
        const siblings = el.parentElement.querySelectorAll('.reveal');
        if (siblings.length > 1) {
            const idx = [...siblings].indexOf(el);
            el.style.transitionDelay = (idx * 0.1) + 's';
        }
        io.observe(el);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
