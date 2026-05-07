</main>
<footer class="footer">
    <div class="footer-main">
        <div class="footer-brand">
            <a href="/" class="footer-logo">Elevion<span>Supply</span></a>
            <p class="footer-bio">Your trusted source for premium tech at wholesale prices. We connect consumers and businesses with the best electronics at unbeatable value.</p>
            <div class="footer-socials">
                <a href="#" class="social" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Products</h4>
            <ul>
                <li><a href="/catalog.php?category=smartphones">Phones</a></li>
                <li><a href="/catalog.php?category=earbuds-audio">Earbuds &amp; Audio</a></li>
                <li><a href="/catalog.php?category=accessories">Accessories</a></li>
                <li><a href="/catalog.php?category=laptops">Laptops</a></li>
                <li><a href="/catalog.php?category=computer-parts">Computer Parts</a></li>
                <li><a href="/catalog.php?category=wearables">Wearables</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Support</h4>
            <ul>
                <li><a href="/help/privacy.php">Privacy Policy</a></li>
                <li><a href="/help/shipping.php">Shipping Policy</a></li>
                <li><a href="/help/returns.php">Return Policy</a></li>
                <li><a href="/help/terms.php">Terms &amp; Conditions</a></li>
                <li><a href="/help/faq.php">FAQ</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Account</h4>
            <ul>
                <li><a href="/account/index.php">My Account</a></li>
                <li><a href="/account/orders.php">Order History</a></li>
                <li><a href="/account/addresses.php">Addresses</a></li>
                <li><a href="/cart.php">Cart</a></li>
                <li><a href="/track.php">Track Order</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Contact Us</h4>
            <div class="footer-contact">
                <p><i class="fas fa-map-marker-alt"></i> 123 Tech Plaza, San Francisco, CA 94102</p>
                <p><i class="fas fa-phone"></i> +1 (800) 555-TECH</p>
                <p><i class="fas fa-envelope"></i> hello@elevionsupply.com</p>
                <p><i class="fas fa-clock"></i> Mon–Fri: 9am – 6pm PST</p>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© <?= date('Y') ?> <span>ElevionSupply</span>. All rights reserved.</p>
        <div class="footer-legal">
            <a href="/help/privacy.php">Privacy</a>
            <a href="/help/terms.php">Terms</a>
            <a href="/sitemap.php">Sitemap</a>
        </div>
    </div>
</footer>

<script src="/assets/js/main.js"></script>
<?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
<script src="/assets/js/<?= e($js) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
