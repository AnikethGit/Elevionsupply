<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
if (is_logged_in()) redirect('/account/index.php');

$error = '';
if (is_post()) {
    verify_csrf_form('/register.php');

    if (post('password') !== post('confirm_password')) $error = 'Passwords do not match';
    else {
        $result = register_user($_POST);
        if ($result['success']) redirect('/account/index.php');
        else $error = $result['message'];
    }
}
$pageTitle = 'Create Account';
$extraCss  = ['auth.css'];
require_once 'includes/header.php';
?>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-logo"><a href="/">Elevion<span>Supply</span></a></div>
        <h1>Create Account</h1>
        <p class="auth-subtitle">Join ElevionSupply today</p>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>
        <form method="POST" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" value="<?= e(post('first_name')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" value="<?= e(post('last_name')) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?= e(post('email')) ?>" required placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= e(post('phone')) ?>" placeholder="+1 (555) 000-0000">
            </div>
            <div class="form-group">
                <label>Password * <small>(min 8 characters)</small></label>
                <input type="password" name="password" required minlength="8" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required placeholder="••••••••">
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" required> I agree to the <a href="/help/terms.php" target="_blank">Terms &amp; Conditions</a>
            </label>
            <button type="submit" class="btn btn-primary btn-lg auth-submit">Create Account</button>
        </form>
        <p class="auth-footer">Already have an account? <a href="/login.php">Sign in</a></p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
