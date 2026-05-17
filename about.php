<?php
$pageTitle       = 'About Us';
$pageDescription = 'Learn about ElevionSupply — our story, mission, and the team behind the UK\'s trusted wholesale electronics platform.';
$extraCss        = ['pages.css'];
require_once 'includes/header.php';
?>

<div class="page-hero">
    <h1>About ElevionSupply</h1>
    <p>Connecting businesses and consumers with premium tech at honest prices</p>
</div>

<!-- STORY -->
<section style="padding:72px 0;background:var(--white)">
    <div class="container">
        <div class="about-grid reveal">
            <div class="about-img-wrap">🏢</div>
            <div class="about-text">
                <span class="tag">Our Story</span>
                <h2>Built for Buyers Who Know What They Want</h2>
                <p>ElevionSupply was founded with a single conviction — great technology shouldn't come with inflated retail margins. We set out to build a platform that gives both individual buyers and wholesale customers direct access to premium electronics at prices that actually make sense.</p>
                <p>Operating from Banchory, Aberdeenshire, we serve customers across the United Kingdom and beyond, offering a curated catalogue of smartphones, laptops, audio gear, accessories, and more — all backed by transparent pricing and real customer support.</p>
            </div>
        </div>

        <div class="about-grid reverse reveal">
            <div class="about-img-wrap">🎯</div>
            <div class="about-text">
                <span class="tag">Our Mission</span>
                <h2>Wholesale Value. Retail Convenience.</h2>
                <p>We believe the gap between wholesale and retail pricing has gone on long enough. Our mission is to close it — giving every customer access to the same competitive prices that were once reserved for bulk buyers and corporate accounts.</p>
                <p>Whether you're an individual picking up your next device or a reseller stocking a storefront, ElevionSupply gives you the pricing, selection, and service you deserve.</p>
            </div>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="stats-bar">
    <div class="container">
        <div class="stats-bar-inner">
            <?php foreach ([
                ['5,000+', 'Happy Customers'],
                ['500+',   'Products Listed'],
                ['48h',    'Avg. Dispatch Time'],
                ['30-Day', 'Hassle-Free Returns'],
            ] as [$num, $lbl]): ?>
            <div class="stat-item reveal">
                <div class="num"><?= $num ?></div>
                <div class="lbl"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- VALUES -->
<section style="padding:72px 0;background:var(--gray-100)">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-label">What We Stand For</div>
            <h2 class="section-title">Our Values</h2>
        </div>
        <div class="values-list">
            <?php foreach ([
                ['fa-shield-alt',     'Trust & Transparency',   'No hidden fees, no bait-and-switch. Every price you see is the price you pay.'],
                ['fa-bolt',           'Speed & Reliability',     'Orders dispatched fast. Tracking every step. Support that actually responds.'],
                ['fa-globe',          'Genuine Products',        'Every item we sell is sourced from verified suppliers. Authenticity guaranteed.'],
                ['fa-handshake',      'Customer First',          'From first click to delivery, your experience is what we optimise for.'],
                ['fa-tags',           'Fair Pricing',            'Wholesale rates available to everyone — no memberships, no gatekeeping.'],
                ['fa-recycle',        'Responsible Commerce',    'We\'re working toward sustainable packaging and carbon-conscious logistics.'],
            ] as [$icon, $title, $desc]): ?>
            <div class="value-card reveal">
                <div class="value-icon"><i class="fas <?= $icon ?>"></i></div>
                <h3><?= $title ?></h3>
                <p><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section style="padding:64px 0;background:var(--white);text-align:center">
    <div class="container reveal">
        <div class="section-label">Ready to Start?</div>
        <h2 class="section-title" style="margin-bottom:16px">Shop the Catalogue</h2>
        <p style="font-size:15px;color:var(--gray-500);margin-bottom:32px;max-width:480px;margin-left:auto;margin-right:auto">Browse hundreds of products with wholesale pricing available to every customer — no account required to view prices.</p>
        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
            <a href="/catalog.php" class="btn btn-primary btn-lg">Browse Products</a>
            <a href="/contact.php" class="btn btn-outline btn-lg">Get in Touch</a>
        </div>
    </div>
</section>

<script>
(function(){
    const io = new IntersectionObserver(entries=>{
        entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); io.unobserve(e.target); } });
    },{threshold:0.12});
    document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
})();
</script>

<?php require_once 'includes/footer.php'; ?>
