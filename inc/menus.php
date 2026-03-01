<?php
/* =========================================================
   UNIFIED MENU LOGIC
   ========================================================= */

function fbl_get_menu_items_by_name($menu_name) {
    $menu = wp_get_nav_menu_object($menu_name);
    if (!$menu) return [];
    return wp_get_nav_menu_items($menu->term_id);
}

function fbl_render_menu_tree($items, $parent_id = 0) {
    $output = '';

    foreach ($items as $item) {
        if ((int) $item->menu_item_parent === (int) $parent_id) {
            $classes   = ['fbl-mm-item', 'menu-item-' . $item->ID];

            $has_children = false;
            foreach ($items as $potential_child) {
                if ((int) $potential_child->menu_item_parent === (int) $item->ID) {
                    $has_children = true;
                    break;
                }
            }
            if ($has_children) {
                $classes[] = 'menu-item-has-children';
            }

            if (!empty($item->classes) && is_array($item->classes)) {
                $classes = array_merge($classes, $item->classes);
            }

            $class_string = implode(' ', array_filter($classes));

            $output .= '<li class="' . esc_attr($class_string) . '">';
            $output .= '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';

            $children = fbl_render_menu_tree($items, $item->ID);
            if ($children) {
                $output .= '<ul class="sub-menu">' . $children . '</ul>';
            }

            $output .= '</li>';
        }
    }

    return $output;
}

function fbl_mobile_menu_shortcode() {
    $left  = fbl_get_menu_items_by_name('Your Experience');
    $right = fbl_get_menu_items_by_name('Our Facilities');

    $output  = '<div class="fbl-mobile-menu-wrapper">';
    $output .= '<div class="fbl-mm-section">';
    $output .= '<h3 class="fbl-mm-heading">Your Experience</h3>';
    $output .= '<ul class="fbl-mm-list">' . fbl_render_menu_tree($left) . '</ul>';
    $output .= '</div>';
    $output .= '<div class="fbl-mm-section">';
    $output .= '<h3 class="fbl-mm-heading">Our Facilities</h3>';
    $output .= '<ul class="fbl-mm-list">' . fbl_render_menu_tree($right) . '</ul>';
    $output .= '</div>';
    $output .= '</div>';

    return $output;
}
add_shortcode('fbl_mobile_menu', 'fbl_mobile_menu_shortcode');

function fbl_footer_mobile_menu_shortcode() {
    $menus  = ['Lodges', 'Outposts', 'Trip Planner', 'Fishing', 'About Us'];
    $output = '<div class="fbl-mobile-menu-wrapper fbl-footer-mm">';

    foreach ($menus as $menu_name) {
        $items = fbl_get_menu_items_by_name($menu_name);
        if (!$items) continue;

        $output .= '<div class="fbl-mm-section">';
        $output .= '<h3 class="fbl-mm-heading">' . esc_html($menu_name) . '</h3>';
        $output .= '<ul class="fbl-mm-list">' . fbl_render_menu_tree($items) . '</ul>';
        $output .= '</div>';
    }

    $output .= '</div>';
    return $output;
}
add_shortcode('fbl_footer_mobile_menu', 'fbl_footer_mobile_menu_shortcode');

/* =========================================================
   MOBILE DRAWERS
   ========================================================= */

add_action('wp_footer', function() {
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return;

    echo '
    <div id="fbl-mm-drawer">
        <button id="fbl-mm-open">
            <span class="fbl-mm-open-icon"></span>
            <span class="fbl-mm-open-label">What We Offer</span>
        </button>
        <button id="fbl-footer-mm-open" class="fbl-mm-secondary-btn">
            <span class="fbl-mm-open-icon"></span>
            <span class="fbl-mm-open-label">Additional Details</span>
        </button>
    </div>

    <div id="fbl-mm-overlay"></div>

    <div id="fbl-mm-bottom-sheet">
        <div class="fbl-mm-sheet-header">
            <span class="fbl-mm-sheet-title">What We Offer</span>
            <button id="fbl-mm-close">
                <span id="fbl-mm-close-icon"></span>
            </button>
        </div>
        <div class="fbl-mm-sheet-body">
            ' . do_shortcode('[fbl_mobile_menu]') . '
        </div>
    </div>

    <div id="fbl-footer-mm-bottom-sheet">
        <div class="fbl-mm-sheet-header">
            <span class="fbl-mm-sheet-title">Additional Details</span>
            <button id="fbl-footer-mm-close">
                <span class="fbl-mm-close-icon"></span>
            </button>
        </div>
        <div class="fbl-mm-sheet-body">
            ' . do_shortcode('[fbl_footer_mobile_menu]') . '
        </div>
    </div>';
});
