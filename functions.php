<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* =========================================================
   FBL DEBUG FLAGS
   ========================================================= */
define('FBL_BREADCRUMB_TEST_MODE', false);

/* =========================================================
   CORE INCLUDES
   ========================================================= */
require_once get_stylesheet_directory() . '/inc/color-tweaker.php';
require_once get_stylesheet_directory() . '/inc/color-tweaker-admin-page.php';
require_once get_stylesheet_directory() . '/inc/enqueue.php';
require_once get_stylesheet_directory() . '/inc/customizer.php';
require_once get_stylesheet_directory() . '/inc/editor-access.php';
require_once get_stylesheet_directory() . '/inc/menus.php';
require_once get_stylesheet_directory() . '/inc/catch-of-day.php';
require_once get_stylesheet_directory() . '/inc/breadcrumbs.php';
require_once get_stylesheet_directory() . '/inc/misc.php';
require_once get_stylesheet_directory() . '/inc/shortcodes.php';
