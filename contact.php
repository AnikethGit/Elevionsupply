<?php
$pageTitle       = 'Contact Us';
$pageDescription = 'Get in touch with the ElevionSupply team. We\'re here to help with orders, wholesale enquiries, and product questions.';
$extraCss        = ['pages.css'];
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/functions.php';
    verify_csrf_form('/contact.php');

    $name    = trim(post('name'));
    $email   = trim(post('email'));
    $subject = trim(post('subject'));
    $message = trim(post('message'));

    if (!$name || !$email || !$subject || !$message) {
        $err = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } else {
        // Log enquiry to database
        require_once 'includes/config.php';
        db()->prepare("INSERT INTO contact_enquiries (name, email, subject, message) VALUES (?,?,?,?)")
           ->execute([$name, $email, $subject, $message]);
        $msg = 'Thank you! We\'ve received your message and will get back to you within 1 business day.';
    }
}

require_once 'includes/header.php';
?>

<div class="page-hero">
    <h1>Get in Touch</h1>
    <p>Questions, wholesale enquiries, or just want to say hello — we'd love to hear from you</p>
</div>

<div class="container">
    <div class="contact-layout">

        <!-- FORM -->
        <div class="contact-form-wrap reveal">
            <h2>Send Us a Message</h2>
            <p>Fill in the form and we'll respond within 1 business day.</p>

            <?php if ($err): ?>
            <div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
            <?php endif; ?>
            <?php if ($msg): ?>
            <div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
            <?php endif; ?>

            <?php if (!$msg): ?>
            <form method="POST" class="contact-form">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" value="<?= e(post('name')) ?>" placeholder="John Smith" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" value="<?= e(post('email')) ?>" placeholder="john@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject" required>
                        <option value="">— Select a topic —</option>
                        <option value="Order Enquiry" <?= post('subject')==='Order Enquiry'?'selected':'' ?>>Order Enquiry</option>
                        <option value="Wholesale / B2B" <?= post('subject')==='Wholesale / B2B'?'selected':'' ?>>Wholesale / B2B</option>
                        <option value="Product Question" <?= post('subject')==='Product Question'?'selected':'' ?>>Product Question</option>
                        <option value="Returns & Refunds" <?= post('subject')==='Returns & Refunds'?'selected':'' ?>>Returns &amp; Refunds</option>
                        <option value="Technical Support" <?= post('subject')==='Technical Support'?'selected':'' ?>>Technical Support</option>
                        <option value="Other" <?= post('subject')==='Other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" rows="6" placeholder="Tell us how we can help…" required
                              style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:var(--radius-md);font-family:var(--font-body);font-size:14px;resize:vertical"><?= e(post('message')) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="align-self:flex-start">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- SIDEBAR -->
        <div class="contact-sidebar">

            <div class="contact-info-card reveal">
                <h3><i class="fas fa-map-marker-alt"></i> Our Location</h3>
                <div class="contact-detail">
                    <i class="fas fa-building"></i>
                    <div>
                        <div class="label">Address</div>
                        <div class="value">12 Highfield Road<br>Banchory, Aberdeenshire<br>AB31 5UN, United Kingdom</div>
                    </div>
                </div>
            </div>

            <div class="contact-info-card reveal">
                <h3><i class="fas fa-phone"></i> Contact Details</h3>
                <div class="contact-detail">
                    <i class="fas fa-phone"></i>
                    <div>
                        <div class="label">Phone</div>
                        <div class="value"><a href="tel:+15186441943" style="color:var(--primary)">+1 518 644 1943</a></div>
                    </div>
                </div>
                <div class="contact-detail">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <div class="label">Email</div>
                        <div class="value"><a href="mailto:hello@elevionsupply.com" style="color:var(--primary)">hello@elevionsupply.com</a></div>
                    </div>
                </div>
                <div class="contact-detail">
                    <i class="fas fa-clock"></i>
                    <div>
                        <div class="label">Hours</div>
                        <div class="value">Mon–Fri, 9am – 6pm GMT</div>
                    </div>
                </div>
            </div>

            <div class="contact-info-card reveal">
                <h3><i class="fas fa-headset"></i> Other Ways to Help</h3>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <a href="/help/faq.php" class="btn btn-outline" style="text-align:center"><i class="fas fa-question-circle"></i> Browse FAQs</a>
                    <a href="/track" class="btn btn-outline" style="text-align:center"><i class="fas fa-shipping-fast"></i> Track an Order</a>
                    <a href="/help/returns.php" class="btn btn-outline" style="text-align:center"><i class="fas fa-undo"></i> Returns Policy</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const io = new IntersectionObserver(entries=>{
        entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); io.unobserve(e.target); } });
    },{threshold:0.12});
    document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
})();
</script>

<?php require_once 'includes/footer.php'; ?>
