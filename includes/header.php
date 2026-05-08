<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$__cartCount = cart_count();
$__cartTotal = cart_total();
$__user      = auth_user();

$pageTitle       = $pageTitle ?? 'ElevionSupply';
$pageDescription = $pageDescription ?? 'Premium tech at wholesale prices';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle === 'ElevionSupply' ? $pageTitle : "$pageTitle | ElevionSupply") ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <!-- CSRF token available to all JS on the page -->
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/globals.css">
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="/assets/css/<?= e($css) ?>">
    <?php endforeach; endif; ?>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    🚚 Free shipping on orders over <span>$150</span> &nbsp;|&nbsp; Wholesale pricing on bulk orders &nbsp;|&nbsp; 30-day returns
</div>

<!-- HEADER -->
<header class="header" id="header">
    <div class="header-inner">
        <!-- Logo -->
        <a href="/" class="logo">Elevion<span>Supply</span></a>

        <!-- Desktop Nav -->
        <nav class="main-nav">
            <a href="/" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/' ? 'active' : '' ?>">Home</a>
            <a href="/catalog.php?category=smartphones" class="nav-link">Phones</a>
            <a href="/catalog.php?category=earbuds-audio" class="nav-link">Audio</a>
            <a href="/catalog.php?category=laptops" class="nav-link">Laptops</a>
            <a href="/catalog.php?category=accessories" class="nav-link">Accessories</a>
            <a href="/catalog.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'catalog') !== false ? 'active' : '' ?>">All Products</a>
            <?php if ($__user && $__user['role'] === 'admin'): ?>
            <a href="/admin/orders.php" class="nav-link" style="color:var(--gold);font-weight:700"><i class="fas fa-shield-alt"></i> Admin</a>
            <?php endif; ?>
        </nav>

        <!-- Right Actions -->
        <div class="header-right">
            <!-- Search -->
            <form class="search-form" action="/catalog.php" method="GET">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search products..." value="<?= e(get('search')) ?>">
            </form>

            <!-- Cart -->
            <a href="/cart.php" class="cart-btn">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-total"><?= money($__cartTotal) ?></span>
                <?php if ($__cartCount > 0): ?>
                <span class="cart-badge"><?= $__cartCount ?></span>
                <?php endif; ?>
            </a>

            <!-- Auth -->
            <?php if ($__user): ?>
            <div class="user-menu">
                <button class="user-btn">
                    <i class="fas fa-user-circle"></i>
                    <span><?= e($__user['first_name']) ?></span>
                    <i class="fas fa-chevron-down" style="font-size:10px"></i>
                </button>
                <div class="dropdown">
                    <a href="/account/index.php" class="drop-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="/account/orders.php" class="drop-item"><i class="fas fa-box"></i> My Orders</a>
                    <a href="/account/addresses.php" class="drop-item"><i class="fas fa-map-marker-alt"></i> Addresses</a>
                    <a href="/account/settings.php" class="drop-item"><i class="fas fa-cog"></i> Settings</a>
                    <?php if ($__user['role'] === 'admin'): ?>
                    <div class="drop-divider"></div>
                    <a href="/admin/orders.php" class="drop-item" style="color:var(--gold)"><i class="fas fa-shield-alt" style="color:var(--gold)"></i> Admin Panel</a>
                    <?php endif; ?>
                    <div class="drop-divider"></div>
                    <!-- Logout uses POST + CSRF to prevent logout CSRF attacks -->
                    <form method="POST" action="/api/auth/logout.php" style="margin:0">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="drop-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font-family:inherit;font-size:inherit;padding:10px 16px">
                            <i class="fas fa-sign-out-alt"></i> Sign Out
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="auth-links">
                <a href="/login.php" class="login-btn">Sign In</a>
                <a href="/register.php" class="register-btn">Register</a>
            </div>
            <?php endif; ?>

            <!-- Mobile Toggle -->
            <button class="menu-toggle" id="menuToggle" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="/" class="mobile-link">Home</a>
        <a href="/catalog.php?category=smartphones" class="mobile-link">Phones</a>
        <a href="/catalog.php?category=earbuds-audio" class="mobile-link">Audio</a>
        <a href="/catalog.php?category=laptops" class="mobile-link">Laptops</a>
        <a href="/catalog.php?category=accessories" class="mobile-link">Accessories</a>
        <a href="/catalog.php" class="mobile-link">All Products</a>
        <div class="mobile-divider"></div>
        <?php if ($__user): ?>
        <a href="/account/index.php" class="mobile-link">My Account</a>
        <a href="/account/orders.php" class="mobile-link">My Orders</a>
        <?php if ($__user['role'] === 'admin'): ?>
        <a href="/admin/orders.php" class="mobile-link" style="color:var(--gold);font-weight:700"><i class="fas fa-shield-alt"></i> Admin Panel</a>
        <?php endif; ?>
        <form method="POST" action="/api/auth/logout.php" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="mobile-link" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font-family:inherit;font-size:inherit">Sign Out</button>
        </form>
        <?php else: ?>
        <a href="/login.php" class="mobile-link">Sign In</a>
        <a href="/register.php" class="mobile-link">Register</a>
        <?php endif; ?>
    </div>
</header>
<main>
