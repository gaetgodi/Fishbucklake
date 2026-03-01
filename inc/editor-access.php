<?php
/* =========================================================
   EDITOR CUSTOMIZER ACCESS - SKIN & COLOR TWEAKER ONLY
   ========================================================= */

// Grant editors Customizer access
add_action('init', function() {
    $editor = get_role('editor');
    if ($editor) {
        $editor->add_cap('customize');
        $editor->add_cap('edit_theme_options');
    }
});

// Hide unwanted Customizer panels/sections for non-admins via CSS
add_action('customize_controls_print_styles', function() {
    if (current_user_can('manage_options')) return;
    ?>
    <style>
        /* Hide Widgets panel */
        #accordion-panel-widgets { display: none !important; }
        /* Hide Global Settings section */
        #accordion-section-fbl_global_vars { display: none !important; }
    </style>
    <?php
});

// Filter Customizer to FBL sections only for editors
add_action('customize_register', function($wp_customize) {
    if (current_user_can('manage_options')) return;

    $allowed = ['fbl_color_tweaker', 'fbl_theme_colors'];

    foreach ($wp_customize->sections() as $section) {
        if (!in_array($section->id, $allowed)) {
            $wp_customize->remove_section($section->id);
        }
    }

    // Set display order
    $tweaker_panel = $wp_customize->get_panel('fbl_color_tweaker');
    if ($tweaker_panel) $tweaker_panel->priority = 20;

}, 9999);

/* =========================================================
   ADMIN BAR — THEME COLORS LINK
   Editors: replaces standard Customize nodes with single link
   Admins: adds Theme Colors link alongside standard nodes
   ========================================================= */

add_action('admin_bar_menu', function($wp_admin_bar) {

    if (current_user_can('manage_options')) {
        // ADMIN — add Theme Colors as top-level link, keep everything else intact
        $wp_admin_bar->add_node(array(
            'id'    => 'fbl_theme_colors',
            'title' => 'Theme Colors',
            'href'  => admin_url('customize.php?autofocus[section]=fbl_theme_colors'),
            'meta'  => array('class' => 'fbl-theme-colors-link'),
        ));

        $wp_admin_bar->add_node(array(
            'id'     => 'fbl_theme_colors_menu',
            'parent' => 'site-name',
            'title'  => 'Theme Colors',
            'href'   => admin_url('customize.php?autofocus[section]=fbl_theme_colors'),
        ));

    } else {
        // EDITOR — replace standard Customize nodes with single Theme Colors link
        $wp_admin_bar->remove_node('customize');
        $wp_admin_bar->remove_node('customize-divi-theme');
        $wp_admin_bar->remove_node('widgets');
        $wp_admin_bar->remove_node('menus');

        $wp_admin_bar->add_node(array(
            'id'    => 'fbl_theme_colors',
            'title' => 'Theme Colors',
            'href'  => admin_url('customize.php?autofocus[section]=fbl_theme_colors'),
            'meta'  => array('class' => 'fbl-theme-colors-link'),
        ));

        $wp_admin_bar->add_node(array(
            'id'     => 'fbl_theme_colors_menu',
            'parent' => 'site-name',
            'title'  => 'Theme Colors',
            'href'   => admin_url('customize.php?autofocus[section]=fbl_theme_colors'),
        ));
    }

}, 99999);

// Hide any remaining Customize nodes from frontend admin bar for editors only
add_action('wp_head', function() {
    if (current_user_can('manage_options')) return;
    echo '<style>#wp-admin-bar-customize, #wp-admin-bar-customize-divi-theme { display: none !important; }</style>';
});

/* =========================================================
   PREVENT SUBSCRIBERS FROM ACCESSING ADMIN
   ========================================================= */

add_action('admin_init', function() {
    if (!current_user_can('edit_posts') && !wp_doing_ajax()) {
        wp_redirect(home_url('/my-account/'));
        exit;
    }
});

// Remove admin bar for subscribers
add_action('after_setup_theme', function() {
    if (!current_user_can('edit_posts')) {
        show_admin_bar(false);
    }
});