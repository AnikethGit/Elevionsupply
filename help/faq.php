<?php
$pageTitle = 'FAQ';
$extraCss  = ['help.css'];
require_once '../includes/header.php';
$sections = [
    'Account & Orders' => [
        ['How do I create an account?','Click "Register" at the top of the page and fill in your details. Account creation is instant!'],
        ['How do I reset my password?','Click "Forgot Password" on the login page. We\'ll send a reset link to your email.'],
        ['How do I cancel my order?','Log into your account, go to My Orders, and click "Cancel" next to your order (if eligible).'],
        ['How long do orders take?','Most orders ship within 24 hours. Standard delivery is 2–5 business days.'],
    ],
    'Shipping & Delivery' => [
        ['Do you offer free shipping?','Yes! Free standard shipping on orders over £150. Otherwise standard shipping is £9.99.'],
        ['Where do you ship?','We ship to all 50 US states, Canada, and select international locations.'],
        ['How do I track my order?','You\'ll receive a tracking number by email. Use our Track Order page to monitor your package.'],
        ['What if my package is lost?','Report within 30 days with your tracking number. We\'ll send a replacement or issue a refund.'],
    ],
    'Returns & Refunds' => [
        ['What is your return policy?','30-day hassle-free returns on all products in original condition with all packaging.'],
        ['How do I return an item?','Go to My Orders, select the item, and click "Request Return". We\'ll email a prepaid shipping label.'],
        ['How long until I get my refund?','After we receive your return (3–5 days), we process the refund within 5–7 business days.'],
    ],
    'Payments' => [
        ['What payment methods do you accept?','Credit/debit cards (Visa, Mastercard, Amex). Secure SSL-encrypted transactions.'],
        ['Is my payment secure?','Yes! We use 256-bit SSL encryption and PCI-compliant payment processors.'],
        ['Do you price match?','We offer competitive pricing. Contact us with a quote and we\'ll consider matching it.'],
    ],
];
?>
<div class="page-hero"><h1>Frequently Asked Questions</h1><p>Find answers to common questions about ElevionSupply</p></div>
<div class="help-page-container">
    <?php foreach ($sections as $section => $faqs): ?>
    <div class="faq-section">
        <h2><?= $section ?></h2>
        <div class="faq-list">
            <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-item" id="faq-<?= $section.$i ?>">
                <button class="faq-question">
                    <span><?= e($faq[0]) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer"><p><?= e($faq[1]) ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="help-contact">
        <h2>Didn't find your answer?</h2>
        <p>Our support team is here to help.</p>
        <div class="help-contact-links">
            <a href="mailto:hello@elevionsupply.com" class="btn btn-primary"><i class="fas fa-envelope"></i> Email Us</a>
            <a href="tel:+18005558324" class="btn btn-outline"><i class="fas fa-phone"></i> Call Us</a>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
