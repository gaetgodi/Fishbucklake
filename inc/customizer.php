<?php
/* =========================================================
   COLOR TWEAKER - OUTPUT TO FRONTEND
   ========================================================= */

add_action('wp_head', function() {
    global $fbl_color_tweaker;
    $fbl_color_tweaker->output_custom_css();
}, 999);

/* =========================================================
   COLOR TWEAKER - CUSTOMIZER REGISTRATION
   ========================================================= */

add_action('customize_register', function($wp_customize) {
    global $fbl_color_tweaker;

    // ---- COLOR TWEAKER PANEL ----
    $wp_customize->add_panel('fbl_color_tweaker', array(
        'title'       => 'Theme Colors & Skins',
        'description' => 'Select a skin and customize its colors',
        'priority'    => 20,
    ));

    // ---- fbl_skin — JS relay only, no UI control ----
    // Required by customizer-controls.js as the postMessage relay for live preview.
    // fbl_skin_picker is the visible dropdown; this setting carries the value to the preview iframe.
    $wp_customize->add_setting('fbl_skin', array(
        'default'           => 'fbl-skin-classic',
        'transport'         => 'postMessage',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    // ---- GLOBAL VARIABLES SECTION ----
    // Only exposes Customizer-safe categories: Typography, Border Radius, Colors, Rates Page.
    // Spacing and Layout are excluded — developer territory.
    $wp_customize->add_section('fbl_global_vars', array(
        'title'       => 'Global Settings (All Skins)',
        'description' => 'Typography, radius, and color settings that apply to all skins',
        'panel'       => 'fbl_color_tweaker',
        'priority'    => 10,
    ));

    $global_vars = $fbl_color_tweaker->parse_customizer_global_variables();

    foreach ($global_vars as $category => $vars) {
        foreach ($vars as $var_key => $var_data) {
            $setting_id  = 'fbl_global_' . str_replace('-', '_', $var_data['name']);
            $saved_value = get_theme_mod($setting_id, $var_data['factory_default']);

            $wp_customize->add_setting($setting_id, array(
                'default'           => $saved_value,
                'transport'         => 'postMessage',
                'sanitize_callback' => 'sanitize_text_field',
            ));

            if ($var_data['type'] === 'color') {
                $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $setting_id, array(
                    'label'       => $var_data['label'],
                    'section'     => 'fbl_global_vars',
                    'description' => $category . ' — Factory: ' . $var_data['factory_default'],
                )));
            } else {
                // size and text types — plain text input with factory value as hint
                $wp_customize->add_control($setting_id, array(
                    'label'       => $var_data['label'],
                    'section'     => 'fbl_global_vars',
                    'type'        => 'text',
                    'description' => $category . ' — Factory: ' . $var_data['factory_default'],
                ));
            }
        }
    }

    // ---- THEME COLORS SECTION ----
    // Single section: skin picker at top, color pickers below
    $current_skin = get_option('fbl_skin', 'fbl-skin-classic');

    $wp_customize->add_section('fbl_theme_colors', array(
        'title'    => 'Theme Colors',
        'panel'    => 'fbl_color_tweaker',
        'priority' => 20,
    ));

    // fbl_skin_picker — the one visible skin dropdown
    $wp_customize->add_setting('fbl_skin_picker', array(
        'default'           => $current_skin,
        'transport'         => 'postMessage',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    $wp_customize->add_control('fbl_skin_picker', array(
        'label'    => 'Active Skin',
        'section'  => 'fbl_theme_colors',
        'type'     => 'select',
        'priority' => 1,
        'choices'  => array(
            'fbl-skin-classic' => 'Classic Dark',
            'fbl-skin-light'   => 'Light Theme',
            'fbl-skin-medium'  => 'Navy Professional',
            'fbl-skin-forest'  => 'Forest Green',
            'fbl-skin-sunset'  => 'Sunset Warm',
            'fbl-skin-lake'    => 'Lake Cool',
            'fbl-skin-brand'   => 'Brand (Custom)',
        ),
    ));

    // Register settings AND controls for ALL skins so JS show/hide works
    foreach ($fbl_color_tweaker->get_themes() as $skin_slug => $skin_name) {
        $theme_vars = $fbl_color_tweaker->parse_theme_variables($skin_slug);

        foreach ($theme_vars as $var_key => $var_data) {
            $setting_id  = 'fbl_theme_' . str_replace('-', '_', $skin_slug . '_' . $var_data['name']);
            $saved_value = $fbl_color_tweaker->get_current_profile($skin_slug, $var_key);
            $default     = $saved_value ? $saved_value : $var_data['factory_default'];

            $wp_customize->add_setting($setting_id, array(
                'default'           => $default,
                'transport'         => 'postMessage',
                'sanitize_callback' => 'sanitize_text_field',
            ));

            if ($var_data['type'] === 'color') {
                $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $setting_id, array(
                    'label'       => $var_data['label'],
                    'section'     => 'fbl_theme_colors',
                    'description' => $var_data['description'] . ' — Factory: ' . $var_data['factory_default'] .
                    ($saved_value ? ' | Saved: ' . $saved_value : ''),
                )));
            }
        }
    }
});

/* =========================================================
   COLOR TWEAKER - SYNC CUSTOMIZER TO OPTIONS ON PUBLISH
   ========================================================= */

add_action('customize_save_after', function() {
    global $fbl_color_tweaker;

    // fbl_skin_picker is the single Customizer source of truth
    $skin = get_theme_mod('fbl_skin_picker', 'fbl-skin-classic');

    // Keep options table and both theme_mods in sync
    update_option('fbl_skin', $skin);
    set_theme_mod('fbl_skin', $skin);
    set_theme_mod('fbl_skin_picker', $skin);

    // Save global variables — use full parse to catch all categories on save
    $global_vars = $fbl_color_tweaker->parse_global_variables();
    foreach ($global_vars as $category => $vars) {
        foreach ($vars as $var_key => $var_data) {
            $setting_id = 'fbl_global_' . str_replace('-', '_', $var_data['name']);
            $value      = get_theme_mod($setting_id, false);
            if ($value) {
                set_theme_mod($setting_id, $value);
            }
        }
    }

    // Save theme-specific color variables to options
    $theme_vars = $fbl_color_tweaker->parse_theme_variables($skin);
    foreach ($theme_vars as $var_key => $var_data) {
        $setting_id = 'fbl_theme_' . str_replace('-', '_', $skin . '_' . $var_data['name']);
        $value      = get_theme_mod($setting_id, false);
        if ($value) {
            $fbl_color_tweaker->save_to_profile($skin, $var_key, $value);
        }
    }
});

/* =========================================================
   COLOR TWEAKER - PREVIEW SCRIPTS
   ========================================================= */

add_action('customize_preview_init', function() {
    wp_enqueue_script(
        'fbl-customizer-preview',
        get_stylesheet_directory_uri() . '/js/customizer-preview.js',
        array('jquery', 'customize-preview'),
        filemtime(get_stylesheet_directory() . '/js/customizer-preview.js'),
        true
    );

    wp_enqueue_script(
        'fbl-color-tweaker-preview',
        get_stylesheet_directory_uri() . '/js/color-tweaker-preview.js',
        array('jquery', 'customize-preview'),
        filemtime(get_stylesheet_directory() . '/js/color-tweaker-preview.js'),
        true
    );
});

/* =========================================================
   COLOR TWEAKER - CONTROLS SCRIPT
   ========================================================= */

add_action('customize_controls_enqueue_scripts', function() {
    global $fbl_color_tweaker;

    wp_enqueue_script(
        'fbl-customizer-controls',
        get_stylesheet_directory_uri() . '/js/customizer-controls.js',
        array('jquery', 'customize-controls'),
        filemtime(get_stylesheet_directory() . '/js/customizer-controls.js'),
        true
    );

    // Pass skin list, ajax url, and reset nonce to JS
    wp_localize_script('fbl-customizer-controls', 'fblCustomizer', array(
        'skins'      => $fbl_color_tweaker->get_themes(),
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'resetNonce' => wp_create_nonce('fbl_reset_skin_nonce'),
    ));
});

/* =========================================================
   COLOR TWEAKER - APPLY SKIN TO BODY CLASS
   ========================================================= */

add_filter('body_class', function($classes) {
    $standalone_skin = get_option('fbl_skin', false);
    $customizer_skin = get_theme_mod('fbl_skin', false);
    $skin = $standalone_skin ? $standalone_skin : ($customizer_skin ? $customizer_skin : 'fbl-skin-classic');
    $classes[] = $skin;
    return $classes;
});

/* =========================================================
   COLOR TWEAKER - AJAX ENDPOINT FOR SKIN COLOR SWITCHING
   ========================================================= */

add_action('wp_ajax_fbl_get_skin_colors', function() {
    global $fbl_color_tweaker;

    $skin       = isset($_GET['skin']) ? sanitize_text_field($_GET['skin']) : 'fbl-skin-classic';
    $theme_vars = $fbl_color_tweaker->parse_theme_variables($skin);

    $colors = [];
    foreach ($theme_vars as $var_key => $var_data) {
        if ($var_data['type'] === 'color') {
            $saved               = $fbl_color_tweaker->get_current_profile($skin, $var_key);
            $setting_id          = 'fbl_theme_' . str_replace('-', '_', $skin . '_' . $var_data['name']);
            $colors[$setting_id] = $saved ? $saved : $var_data['factory_default'];
        }
    }

    wp_send_json_success($colors);
});

/* =========================================================
   COLOR TWEAKER - RESET SKIN AJAX ENDPOINT
   ========================================================= */

add_action('wp_ajax_fbl_reset_skin', function() {
    check_ajax_referer('fbl_reset_skin_nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    global $fbl_color_tweaker;
    $skin = isset($_POST['skin']) ? sanitize_text_field($_POST['skin']) : '';

    if (empty($skin)) {
        wp_send_json_error('No skin specified.');
    }

    $fbl_color_tweaker->reset_skin_profile($skin);
    wp_send_json_success('Skin reset successfully.');
});

/* =========================================================
   FBL SKIN SELECTOR - ADMIN MENU LINK TO CUSTOMIZER
   ========================================================= */

add_action('admin_menu', function() {
    add_menu_page(
        'Theme Colors & Skins',
        'Theme Colors',
        'edit_pages',
        'customize.php?autofocus[section]=fbl_theme_colors',
        '',
        'dashicons-admin-customizer',
        30
    );
});