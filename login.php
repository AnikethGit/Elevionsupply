<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
if (is_logged_in()) redirect('/account/index.php');

$error    = '';
$redirect = get('redirect', '/account/index.php');

if (is_post()) {
    // Verify CSRF before processing credentials
    verify_csrf_form('/login.php');

    $result = login_user(post('email'), post('password'));
    if ($result['success']) redirect($redirect);
    else $error = $result['message'];
}

$pageTitle = 'Sign In';
$extraCss  = ['auth.css'];
require_once 'includes/header.php';
?>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-logo"><a href="/">Elevion<span>Supply</span></a></div>
        <h1>Welcome Back</h1>
        <p class="auth-subtitle">Sign in to your account</p>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>
        <form method="POST" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= e(post('email')) ?>" required autofocus placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label style="display:flex;justify-content:space-between;align-items:center">
                    Password
                    <a href="/forgot-password.php" style="font-size:12px;font-weight:600;color:var(--accent-dark)">Forgot password?</a>
                </label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary btn-lg auth-submit">Sign In</button>
        </form>
        <p class="auth-footer">Don't have an account? <a href="/register.php">Register here</a></p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
