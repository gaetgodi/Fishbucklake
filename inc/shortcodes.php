<?php
/**
 * FBL Custom Shortcodes
 * Site: fishbucklake.com
 */

/* =========================================================
   [fbl_login_form]
   Renders a styled login form with register and lost
   password links. Redirects to home page after login.
   Usage: [fbl_login_form]
   ========================================================= */

add_shortcode('fbl_login_form', function() {

    // If already logged in, show a friendly message
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $logout_url   = wp_logout_url(home_url('/'));
        return '<div class="fbl-login-box fbl-already-logged-in">
            <p class="fbl-welcome-text">Welcome back, <strong>' . esc_html($current_user->display_name) . '</strong>.</p>
            <p><a href="' . esc_url($logout_url) . '" class="fbl-login-btn">Log Out</a></p>
        </div>';
    }

    // Handle login errors passed via URL
    $error_message = '';
    if (isset($_GET['login']) && $_GET['login'] === 'failed') {
        $error_message = '<div class="fbl-login-error">Invalid username or password. Please try again.</div>';
    }
    if (isset($_GET['login']) && $_GET['login'] === 'empty') {
        $error_message = '<div class="fbl-login-error">Please enter your username and password.</div>';
    }

    // Build the login form
    $args = [
        'echo'           => false,
        'redirect'       => home_url('/'),
        'form_id'        => 'fbl-login-form',
        'label_username' => 'Username or Email',
        'label_password' => 'Password',
        'label_remember' => 'Remember Me',
        'label_log_in'   => 'Log In',
        'remember'       => true,
    ];

    $form = wp_login_form($args);

    // Register link — only show if registration is enabled in WordPress settings
    $register_url = '';
    if (get_option('users_can_register')) {
        $register_url = '<span class="fbl-register-link"><a href="' . esc_url(wp_registration_url()) . '">Create an Account</a></span>';
    }

    $lost_pw_url = '<span class="fbl-lostpw-link"><a href="' . esc_url(wp_lostpassword_url(get_permalink())) . '">Lost your password?</a></span>';

    $links = '<div class="fbl-login-links">'
        . $register_url
        . $lost_pw_url
        . '</div>';

    return '<div class="fbl-login-box">'
        . $error_message
        . $form
        . $links
        . '</div>';
});

/* =========================================================
   LOGOUT REDIRECT
   Redirect all logouts to home page instead of wp-login.php
   Covers both admin bar logout and fbl_login_form logout link
   ========================================================= */

add_filter('logout_redirect', function($redirect_to, $requested_redirect_to, $user) {
    return home_url('/');
}, 10, 3);