<?php $pageTitle='Sitemap'; $extraCss=['help.css']; require_once 'includes/header.php'; ?>
<div class="page-hero"><h1>Sitemap</h1><p>Complete navigation map of ElevionSupply</p></div>
<div class="help-page-container">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:32px;margin-bottom:48px">
        <?php foreach ([
            'Main'     => ['Home'=>'/','Catalog'=>'/catalog.php','Track Order'=>'/track.php','Sitemap'=>'/sitemap.php'],
            'Shopping' => ['Product Detail'=>'/product.php?id=1','Shopping Cart'=>'/cart.php','Checkout'=>'/checkout.php'],
            'Account'  => ['Dashboard'=>'/account/index.php','My Orders'=>'/account/orders.php','Addresses'=>'/account/addresses.php','Settings'=>'/account/settings.php','Order Detail'=>'/orders/detail.php?id=1'],
            'Auth'     => ['Sign In'=>'/login.php','Register'=>'/register.php','Forgot Password'=>'/forgot-password.php','Reset Password'=>'/reset-password.php'],
            'Help'     => ['FAQ'=>'/help/faq.php','Privacy Policy'=>'/help/privacy.php','Shipping Policy'=>'/help/shipping.php','Returns Policy'=>'/help/returns.php','Terms & Conditions'=>'/help/terms.php'],
        ] as $section => $links): ?>
        <div>
            <h2 style="font-size:16px;color:var(--primary);margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--accent)"><?= $section ?></h2>
            <ul style="display:flex;flex-direction:column;gap:8px;list-style:none">
                <?php foreach ($links as $label => $href): ?>
                <li><a href="<?= $href ?>" style="font-size:14px;color:var(--gray-600);padding:6px 10px;border-radius:6px;display:inline-block;transition:all .2s"><?= $label ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
