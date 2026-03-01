<?php
/**
 * FBL Color Tweaker System
 * Allows dynamic customization of CSS variables per theme
 */

class FBL_Color_Tweaker {

    public function __construct() {
        // Intentionally silent — remove error_log in production
    }

    private $themes = [
        'fbl-skin-classic' => 'Classic Dark',
        'fbl-skin-light'   => 'Light',
        'fbl-skin-medium'  => 'Navy Professional',
        'fbl-skin-forest'  => 'Forest Green',
        'fbl-skin-sunset'  => 'Sunset Warm',
        'fbl-skin-lake'    => 'Lake Cool',
        'fbl-skin-brand'   => 'Brand (Custom)',
    ];

    private $customizer_categories = [
        'Typography',
        'Border Radius',
        'Colors',
        'Rates Page',
    ];

    private $skin_token_info = [
        /* ---- BRAND COLORS ---- */
        'gold' => [
            'label'       => 'Gold — Primary Accent',
            'description' => 'The most impactful color. Changes: all header headings (Hornepayne, FLY-IN, contact area), all 5 footer column headings, all footer menu links, left and right sidebar menu links and headings, element borders and dividers.',
        ],
        'light' => [
            'label'       => 'Light — Body Text',
            'description' => 'Main readable text on dark backgrounds. Changes: paragraph text, intro column text, contact info in header. On the Light skin this is the page background instead.',
        ],
        /* ---- BACKGROUNDS ---- */
        'bg-main' => [
            'label'       => 'Background — Main Page',
            'description' => 'The primary background behind all page content. Changes: the full page background visible behind the three-column content row.',
        ],
        'footer-bg' => [
            'label'       => 'Background — Footer',
            'description' => 'The background of the entire footer area. Changes: the 5-column menu row in the footer and the footer section as a whole.',
        ],
        'sidemenu-bg' => [
            'label'       => 'Background — Sidebar Columns',
            'description' => 'Background of the left and right sidebar columns on every page. Changes: the column behind the Your Experience menu (left) and the secondary navigation menu (right).',
        ],
        'mm-bg' => [
            'label'       => 'Background — Mobile Menu',
            'description' => 'Background of the dropdown navigation menu on mobile devices.',
        ],
        'content-bg' => [
            'label'       => 'Background — Content Column',
            'description' => 'The neutral grey background behind the main content area on every page, and the copyright/legal bar at the bottom of the footer.',
        ],
        'header-bg' => [
            'label'       => 'Background — Header',
            'description' => 'Background color of the site header section. Changes: the full header area including logo, site name, and contact information.',
        ],
        'bg-alt' => [
            'label'       => 'Background — Alt Section',
            'description' => 'Background color of alternate content sections. Changes: the Members of Ontario section and any other sections using the alt background token.',
        ],
        /* ---- UI COMPONENT BACKGROUNDS ---- */
        'form-bg' => [
            'label'       => 'Background — Form Box',
            'description' => 'Background of the login and contact form container. Sits between the page background and the input fields to create visual layering.',
        ],
        'input-bg' => [
            'label'       => 'Background — Form Inputs',
            'description' => 'Background of all form input fields, textareas, and select dropdowns. Should be lighter than the form box for visual depth.',
        ],
        'drawer-bg' => [
            'label'       => 'Background — Mobile Drawer',
            'description' => 'Background of the mobile navigation drawer sheets. Should contrast with the dark page background so menu links are readable.',
        ],
        /* ---- TEXT ---- */
        'text-main' => [
            'label'       => 'Text — Body',
            'description' => 'Main body text color across all pages. Changes: paragraph text, intro text, contact information.',
        ],
        'text-heading' => [
            'label'       => 'Text — Headings',
            'description' => 'General heading text color. Changes: page content headings (H1–H4) in the main content column.',
        ],
        /* ---- FOOTER ---- */
        'footer-text' => [
            'label'       => 'Footer — Text & Links',
            'description' => 'All text and link colors in the footer. Changes: the 5 footer column headings (Lodges, Outposts, Trip Planning, About, Fishing) and all footer menu links.',
        ],
        'footer-link-hover' => [
            'label'       => 'Footer — Link Hover',
            'description' => 'Color footer links turn when hovered. Changes: hover state on all 5 footer menu columns.',
        ],
        /* ---- MENUS ---- */
        'sidemenu-link' => [
            'label'       => 'Menu — Link Color',
            'description' => 'Color of all links in every menu on the site. Changes: sidebar menus (left and right), footer menus, and any other navigation menus.',
        ],
        'topmenu-hover' => [
            'label'       => 'Menu — Top Level Hover',
            'description' => 'Color top-level menu items turn when hovered. Intentionally distinct from sub-menu hover to create a visual hierarchy. Changes: hover state on top-level items in sidebar menus, footer menus, and all site navigation.',
        ],
        'sidemenu-link-hover' => [
            'label'       => 'Menu — Sub-menu Hover',
            'description' => 'Color sub-menu items turn when hovered. Intentionally distinct from top-level hover. Changes: hover state on sub-menu items in sidebar menus and footer menus.',
        ],
        'sidemenu-link-active' => [
            'label'       => 'Menu — Active Item',
            'description' => 'Color of the current/active page menu item. Changes: the active state on all menu items in sidebar menus and footer menus, both top-level and sub-menu.',
        ],
        /* ---- ACCENTS ---- */
        'menu-separator' => [
            'label'       => 'Menu — Separator Line',
            'description' => 'Color of the divider line between top-level menu items and their sub-menus in the sidebar columns.',
        ],
        'border-color' => [
            'label'       => 'Border — Primary',
            'description' => 'Main border and divider color. Changes: section dividers, box borders, FAQ accordion borders, testimonial card borders.',
        ],
    ];

    public function parse_theme_variables($theme_slug) {
        $css_file = get_stylesheet_directory() . '/css/00-tokens.css';
        if (!file_exists($css_file)) return [];
        $css_content = file_get_contents($css_file);
        $variables   = [];
        $pattern = '/body\.' . preg_quote($theme_slug, '/') . '\s*{([^}]+)}/s';
        if (preg_match($pattern, $css_content, $matches)) {
            $theme_block = $matches[1];
            preg_match_all('/--fbl-([a-z-]+):\s*([^;]+);/i', $theme_block, $var_matches, PREG_SET_ORDER);
            foreach ($var_matches as $match) {
                $var_name  = $match[1];
                $var_value = trim($match[2]);
                $var_value = trim(preg_replace("/\/\\*.*?\\*\//", "", $var_value));
                if (strpos($var_value, 'var(') === false) {
                    if (!isset($this->skin_token_info[$var_name])) continue;
                    $info = $this->make_skin_label($var_name);
                    $variables['--fbl-' . $var_name] = [
                        'name'            => $var_name,
                        'label'           => $info['label'],
                        'description'     => $info['description'],
                        'factory_default' => $var_value,
                        'type'            => $this->detect_variable_type($var_value),
                    ];
                }
            }
        }
        return $variables;
    }

    private function make_skin_label($var_name) {
        if (isset($this->skin_token_info[$var_name])) {
            $info = $this->skin_token_info[$var_name];
            $info['description'] .= ' [--fbl-' . $var_name . ']';
            return $info;
        }
        return ['label' => ucwords(str_replace('-', ' ', $var_name)), 'description' => ''];
    }

    private function detect_variable_type($value) {
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) return 'color';
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(/i', $value)) return 'color';
        if (preg_match('/^\d+(\.\d+)?(px|em|rem|%|vh|vw)$/', $value)) return 'size';
        return 'text';
    }

    public function get_current_profile($theme_slug, $var_name) {
        $option_name = 'fbl_tweaker_' . $theme_slug . '_' . str_replace('--fbl-', '', $var_name);
        return get_option($option_name, false);
    }

    public function save_to_profile($theme_slug, $var_name, $value) {
        $option_name = 'fbl_tweaker_' . $theme_slug . '_' . str_replace('--fbl-', '', $var_name);
        update_option($option_name, $value);
    }

    public function get_themes() {
        return $this->themes;
    }

    public function parse_global_variables() {
        $css_file = get_stylesheet_directory() . '/css/00-tokens.css';
        if (!file_exists($css_file)) return [];
        $css_content = file_get_contents($css_file);
        $variables   = [];
        if (preg_match('/:root\s*{([^}]+)}/s', $css_content, $matches)) {
            $root_block = $matches[1];
            preg_match_all('/--fbl-([a-z-]+):\s*([^;]+);/i', $root_block, $var_matches, PREG_SET_ORDER);
            foreach ($var_matches as $match) {
                $var_name  = $match[1];
                $var_value = trim($match[2]);
                if (strpos($var_value, 'var(') === false) {
                    $category = $this->categorize_global_variable($var_name);
                    if (!isset($variables[$category])) $variables[$category] = [];
                    $variables[$category]['--fbl-' . $var_name] = [
                        'name'            => $var_name,
                        'label'           => $this->make_label($var_name),
                        'factory_default' => $var_value,
                        'type'            => $this->detect_variable_type($var_value),
                    ];
                }
            }
        }
        return $variables;
    }

    public function parse_customizer_global_variables() {
        $all = $this->parse_global_variables();
        return array_intersect_key($all, array_flip($this->customizer_categories));
    }

    private function categorize_global_variable($var_name) {
        if (strpos($var_name, 'font') === 0) return 'Typography';
        if (strpos($var_name, 'space') === 0) return 'Spacing';
        if (strpos($var_name, 'radius') === 0) return 'Border Radius';
        if (strpos($var_name, 'site-max-width') !== false || strpos($var_name, 'content') === 0) return 'Layout';
        if (strpos($var_name, 'body-size') !== false || strpos($var_name, 'title-size') !== false ||
            strpos($var_name, 'bottom-size') !== false || strpos($var_name, 'line-height') !== false) return 'Typography';
        if (strpos($var_name, 'link') !== false || strpos($var_name, 'menu-separator') !== false) return 'Colors';
        if (strpos($var_name, 'tan-box') !== false || strpos($var_name, 'green-arrow') !== false ||
            strpos($var_name, 'box-border') !== false || strpos($var_name, 'rates-text') !== false ||
            strpos($var_name, 'bright-gold') !== false) return 'Rates Page';
        return 'Other';
    }

    private function make_label($var_name) {
        $labels = [
            'font-body'      => 'Body Font Stack',      'font-heading'  => 'Heading Font Stack',
            'font-sm'        => 'Font Size: Small',      'font-md'       => 'Font Size: Medium',
            'font-lg'        => 'Font Size: Large',      'font-xl'       => 'Font Size: Extra Large',
            'body-size'      => 'Body Text Size',        'title-size'    => 'Title Text Size',
            'bottom-size'    => 'Large Heading Size',    'line-height'   => 'Line Height',
            'radius'         => 'Border Radius: Standard', 'radius-lg'  => 'Border Radius: Large',
            'link'           => 'Link Color',            'menu-separator' => 'Menu Separator Color',
            'tan-box'        => 'Rates Box Background',  'green-arrow'   => 'Arrow Bullet Color',
            'box-border'     => 'Rates Box Border',      'rates-text'    => 'Rates Box Text',
            'bright-gold'    => 'Rates Heading Color',   'content-bg'    => 'Content Column Background',
        ];
        return isset($labels[$var_name]) ? $labels[$var_name] : ucwords(str_replace('-', ' ', $var_name));
    }

    public function output_custom_css() {
        $current_skin = get_option('fbl_skin', 'fbl-skin-classic');
        $theme_vars   = $this->parse_theme_variables($current_skin);
        $global_vars  = $this->parse_global_variables();
        $custom_css   = "<style id='fbl-color-tweaker-custom'>\n";
        $has_customizations = false;

        $custom_css .= ":root {\n";
        foreach ($global_vars as $category => $vars) {
            foreach ($vars as $var_key => $var_data) {
                $setting_id   = 'fbl_global_' . str_replace('-', '_', $var_data['name']);
                $custom_value = get_theme_mod($setting_id, false);
                if ($custom_value && $custom_value !== $var_data['factory_default']) {
                    $custom_css .= "    {$var_key}: {$custom_value};\n";
                    $has_customizations = true;
                }
            }
        }
        $custom_css .= "}\n";

        $custom_css .= "body.{$current_skin} {\n";
        foreach ($theme_vars as $var_key => $var_data) {
            $setting_id   = 'fbl_theme_' . str_replace('-', '_', $current_skin . '_' . $var_data['name']);
            $custom_value = $this->get_current_profile($current_skin, $var_key);
            if (!$custom_value) $custom_value = get_theme_mod($setting_id, false);
            if ($custom_value && $custom_value !== $var_data['factory_default']) {
                $custom_css .= "    {$var_key}: {$custom_value};\n";
                $has_customizations = true;
            }
        }
        $custom_css .= "}\n</style>\n";

        if ($has_customizations) echo $custom_css;
    }

    public function reset_skin_profile($skin_slug) {
        $theme_vars = $this->parse_theme_variables($skin_slug);
        foreach ($theme_vars as $var_key => $var_data) {
            delete_option('fbl_tweaker_' . $skin_slug . '_' . $var_data['name']);
            remove_theme_mod('fbl_theme_' . str_replace('-', '_', $skin_slug . '_' . $var_data['name']));
        }
        return true;
    }
}

// Initialize
$GLOBALS['fbl_color_tweaker'] = new FBL_Color_Tweaker();