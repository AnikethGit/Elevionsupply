<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
if (is_logged_in()) redirect('/account/index.php');

$token = trim(get('token'));
$msg   = '';
$err   = '';
$valid = false;

if ($token) {
    $stmt = db()->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    $valid = (bool)$reset;
    if (!$valid) $err = 'This reset link is invalid or has expired.';
}

if ($token && $valid && is_post()) {
    verify_csrf_form('/reset-password.php?token=' . urlencode($token));

    $pass    = post('password');
    $confirm = post('confirm_password');

    if (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $err = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);
        db()->prepare("UPDATE users SET password_hash=? WHERE email=?")
           ->execute([$hash, $reset['email']]);
        db()->prepare("DELETE FROM password_resets WHERE token=?")
           ->execute([$token]);
        $msg   = 'Password updated! You can now sign in.';
        $valid = false;
    }
}

$pageTitle = 'Reset Password';
$extraCss  = ['auth.css'];
require_once 'includes/header.php';
?>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-logo"><a href="/">Elevion<span>Supply</span></a></div>
        <h1>New Password</h1>
        <p class="auth-subtitle">Choose a strong password for your account</p>

        <?php if ($err): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>
        <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>

        <?php if ($valid): ?>
        <form method="POST" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>New Password <small>(min 8 characters)</small></label>
                <input type="password" name="password" required minlength="8" placeholder="••••••••" autofocus>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary btn-lg auth-submit">Update Password</button>
        </form>
        <?php elseif (!$token || (!$valid && !$msg)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Invalid or missing reset token.</div>
        <?php endif; ?>

        <p class="auth-footer">
            <?php if ($msg): ?>
                <a href="/login.php">Sign in →</a>
            <?php else: ?>
                <a href="/forgot-password.php">Request a new link</a>
            <?php endif; ?>
        </p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
