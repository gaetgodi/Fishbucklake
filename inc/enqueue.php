<?php
/* =========================================================
   ENQUEUE SCRIPTS & STYLES
   ========================================================= */

add_action('wp_enqueue_scripts', function() {
    /* =========================================================
       FBL STYLESHEET STACK
       All CSS files enqueued directly (no @import)
       Order: tokens → global → specific modules
       ========================================================= */

    wp_enqueue_style('fbl-tokens',
        get_stylesheet_directory_uri() . '/css/00-tokens.css',
        array('divi-style'),
        filemtime(get_stylesheet_directory() . '/css/00-tokens.css') . '-' . get_option('fbl_skin', 'fbl-skin-classic')
    );

    wp_enqueue_style('fbl-global',
        get_stylesheet_directory_uri() . '/css/01-global.css',
        array('fbl-tokens'),
        filemtime(get_stylesheet_directory() . '/css/01-global.css')
    );

    wp_enqueue_style('fbl-layout',
        get_stylesheet_directory_uri() . '/css/02-layout.css',
        array('fbl-global'),
        filemtime(get_stylesheet_directory() . '/css/02-layout.css')
    );

    wp_enqueue_style('fbl-side-menus',
        get_stylesheet_directory_uri() . '/css/03-side-menus.css',
        array('fbl-global'),
        filemtime(get_stylesheet_directory() . '/css/03-side-menus.css')
    );

    wp_enqueue_style('fbl-footer',
        get_stylesheet_directory_uri() . '/css/04-footer.css',
        array('fbl-global'),
        filemtime(get_stylesheet_directory() . '/css/04-footer.css')
    );

    wp_enqueue_style('fbl-drawers',
        get_stylesheet_directory_uri() . '/css/05-drawers.css',
        array('fbl-global'),
        filemtime(get_stylesheet_directory() . '/css/05-drawers.css')
    );

    wp_enqueue_style('fbl-components',
        get_stylesheet_directory_uri() . '/css/06-components.css',
        array('fbl-global'),
        filemtime(get_stylesheet_directory() . '/css/06-components.css')
    );

    wp_enqueue_style('fbl-pages',
        get_stylesheet_directory_uri() . '/css/07-pages.css',
        array('fbl-global'),
        filemtime(get_stylesheet_directory() . '/css/07-pages.css')
    );

    wp_enqueue_style('fbl-motopress',
        get_stylesheet_directory_uri() . '/css/08-motopress.css',
        array('fbl-pages'),
        filemtime(get_stylesheet_directory() . '/css/08-motopress.css')
    );

    wp_enqueue_style('fbl-login',
        get_stylesheet_directory_uri() . '/css/09-login.css',
        array('fbl-pages'),
        filemtime(get_stylesheet_directory() . '/css/09-login.css')
    );

    // Don't load custom JS in Visual Builder (CSS already loaded above)
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) {
        return;
    }

    // Mobile drawer JavaScript
    wp_enqueue_script(
        'fbl-mobile-drawer',
        get_stylesheet_directory_uri() . '/fbl-mobile-drawer.js',
        array(),
        filemtime(get_stylesheet_directory() . '/fbl-mobile-drawer.js'),
        true
    );

    // FAQ System (CSS + JavaScript - only on FAQ page)
    if (is_page('faqs')) {
        wp_enqueue_style(
            'fbl-faq',
            get_stylesheet_directory_uri() . '/css/faq.css',
            array(),
            filemtime(get_stylesheet_directory() . '/css/faq.css')
        );

        wp_enqueue_script(
            'faq-system',
            get_stylesheet_directory_uri() . '/js/faq-system.js',
            array(),
            filemtime(get_stylesheet_directory() . '/js/faq-system.js'),
            true
        );
    }
}, 999);

/* =========================================================
   PAGE-SPECIFIC CSS LOADING
   ========================================================= */

   if (is_page('login') || is_page('my-account')) {
    wp_enqueue_style('fbl-login', get_stylesheet_directory_uri() . '/css/login.css');
}

add_action('wp_enqueue_scripts', 'fbl_load_page_specific_css', 20);

function fbl_load_page_specific_css() {
    if (!is_page()) return;

    $page_id   = get_the_ID();
    $page_slug = get_post_field('post_name', $page_id);

    $css_file_slug = get_stylesheet_directory() . '/css/page-' . $page_slug . '.css';

    if (file_exists($css_file_slug)) {
        wp_enqueue_style(
            'fbl-page-' . $page_slug,
            get_stylesheet_directory_uri() . '/css/page-' . $page_slug . '.css',
            array('fbl-pages'),
            filemtime($css_file_slug)
        );
        return;
    }

    $css_file_id = get_stylesheet_directory() . '/css/page-' . $page_id . '.css';

    if (file_exists($css_file_id)) {
        wp_enqueue_style(
            'fbl-page-id-' . $page_id,
            get_stylesheet_directory_uri() . '/css/page-' . $page_id . '.css',
            array('fbl-pages'),
            filemtime($css_file_id)
        );
    }
}

/* =========================================================
   TESTIMONIALS PAGE
   ========================================================= */

add_action('wp_enqueue_scripts', function() {
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return;

    if (is_page('testimonials')) {
        wp_enqueue_style(
            'fbl-testimonials',
            get_stylesheet_directory_uri() . '/css/testimonials.css',
            array('fbl-pages'),
            filemtime(get_stylesheet_directory() . '/css/testimonials.css')
        );

        wp_enqueue_script(
            'fbl-testimonials',
            get_stylesheet_directory_uri() . '/js/testimonials.js',
            array(),
            filemtime(get_stylesheet_directory() . '/js/testimonials.js'),
            true
        );
    }
}, 20);

/* =========================================================
   SPORT SHOWS PAGE
   ========================================================= */

add_action('wp_enqueue_scripts', function() {
    if (is_page('2026-trade-shows') || is_page('sport-shows')) {
        wp_enqueue_style(
            'fbl-sport-shows',
            get_stylesheet_directory_uri() . '/css/sport-shows.css',
            array('fbl-pages'),
            filemtime(get_stylesheet_directory() . '/css/sport-shows.css')
        );
    }
}, 20);

/* =========================================================
   CONTACT PAGE
   ========================================================= */

add_action('wp_enqueue_scripts', function() {
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return;

    if (is_page('contact') || is_page('contact-us')) {
        wp_enqueue_style(
            'fbl-contact-form',
            get_stylesheet_directory_uri() . '/css/contact-form.css',
            array('fbl-pages'),
            filemtime(get_stylesheet_directory() . '/css/contact-form.css')
        );

        wp_enqueue_script(
            'fbl-contact-form',
            get_stylesheet_directory_uri() . '/js/contact-form.js',
            array(),
            filemtime(get_stylesheet_directory() . '/js/contact-form.js'),
            true
        );
    }
}, 20);

/* =========================================================
   CATCH OF THE DAY - SCRIPTS & STYLES
   ========================================================= */

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'fbl-catch-of-day',
        get_stylesheet_directory_uri() . '/js/catch-of-the-day.js',
        array(),
        filemtime(get_stylesheet_directory() . '/js/catch-of-the-day.js'),
        true
    );

    wp_enqueue_style(
        'fbl-catch-of-day',
        get_stylesheet_directory_uri() . '/css/catch-of-the-day.css',
        array('fbl-components'),
        filemtime(get_stylesheet_directory() . '/css/catch-of-the-day.css')
    );
});

/* =========================================================
   CATCH GALLERY - LIGHTBOX SUPPORT
   ========================================================= */

add_action('wp_enqueue_scripts', function() {
    global $post;

    $needs_fancybox = is_page_template('page-catch-gallery.php') ||
                      (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'catch_gallery'));

    if ($needs_fancybox) {
        wp_enqueue_script(
            'fancybox',
            'https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.umd.js',
            array(),
            '4.0',
            true
        );

        wp_enqueue_style(
            'fancybox',
            'https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.css',
            array(),
            '4.0'
        );

        wp_add_inline_script('fancybox', 'document.addEventListener("DOMContentLoaded", function() { Fancybox.bind("[data-fancybox]", {}); });');
    }
});