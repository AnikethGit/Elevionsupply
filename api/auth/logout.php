<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Logout must be a POST to prevent CSRF-driven session termination via img/link tags
if (!is_post()) {
    redirect('/');
}

verify_csrf_form('/');
logout_user();
redirect('/');
