<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
if (is_logged_in()) redirect('/account/index.php');

$msg = '';
$err = '';

if (is_post()) {
    verify_csrf_form('/forgot-password.php');
    $email = strtolower(trim(post('email')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } else {
        // Always show success — don't reveal whether email exists
        $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            // Delete any existing token for this email
            db()->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            db()->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
               ->execute([$email, $token, $expiresAt]);

            // In production this would send an email.
            // For this demo the reset link is shown directly.
            $_SESSION['reset_demo_link'] = '/reset-password.php?token=' . $token;
        }
        $msg = 'If that email is registered you will receive a reset link shortly.';
    }
}

$demoLink  = $_SESSION['reset_demo_link'] ?? null;
unset($_SESSION['reset_demo_link']);

$pageTitle = 'Forgot Password';
$extraCss  = ['auth.css'];
require_once 'includes/header.php';
?>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-logo"><a href="/">Elevion<span>Supply</span></a></div>
        <h1>Reset Password</h1>
        <p class="auth-subtitle">Enter your email and we'll send you a reset link</p>

        <?php if ($err):  ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>
        <?php if ($msg):  ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>
        <?php if ($demoLink): ?>
        <div class="alert alert-info" style="word-break:break-all">
            <i class="fas fa-info-circle"></i>
            <span><strong>Demo only — no email sent.</strong> Your reset link:<br>
            <a href="<?= e($demoLink) ?>"><?= e($demoLink) ?></a></span>
        </div>
        <?php endif; ?>

        <?php if (!$msg): ?>
        <form method="POST" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= e(post('email')) ?>" required autofocus placeholder="you@example.com">
            </div>
            <button type="submit" class="btn btn-primary btn-lg auth-submit">Send Reset Link</button>
        </form>
        <?php endif; ?>

        <p class="auth-footer"><a href="/login.php">← Back to Sign In</a></p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
