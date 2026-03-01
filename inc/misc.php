<?php
/* =========================================================
   AUTO-SCROLL ON PAGE LOAD
   ========================================================= */

add_action('wp_footer', function() {
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return;
    ?>
    <script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            let header = document.querySelector('#fbl-sticky-header-group');
            if (header) {
                let headerHeight = header.getBoundingClientRect().height;
                window.scrollTo({
                    top: headerHeight - 350,
                    behavior: 'smooth'
                });
            }
        }, 100);
    });
    </script>
    <?php
});

/* =========================================================
   PREVENT ACTIVITIES LINK SCROLL
   ========================================================= */

add_action('wp_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const activitiesLink = document.querySelector('.menu-item-221224 > a');
        if (activitiesLink) {
            activitiesLink.addEventListener('click', function(e) {
                e.preventDefault();
                return false;
            });
        }
    });
    </script>
    <?php
});

/* =========================================================
   ACTIVE LINK HIGHLIGHTER — URL-BASED, ALL MENUS
   Adds .fbl-active-link to any anchor whose href matches
   the current page URL, across side menus and footer menus.
   Works regardless of which menu WordPress marks as active.
   ========================================================= */

add_action('wp_footer', function() {
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var current = window.location.href.replace(/\/$/, '');
        var selectors = [
            '#fbl-left-menu a',
            '#fbl-right-menu a',
            '#fbl-footer-menus a',
        ];
        selectors.forEach(function(sel) {
            document.querySelectorAll(sel).forEach(function(a) {
                var href = (a.getAttribute('href') || '').replace(/\/$/, '');
                if (href && href === current) {
                    a.classList.add('fbl-active-link');
                }
            });
        });
    });
    </script>
    <?php
});

/* =========================================================
   HIDE MOTOPRESS ACCOMMODATION MENU FROM EDITORS
   ========================================================= */

add_action('admin_menu', function() {
    if (current_user_can('editor') && !current_user_can('administrator')) {
        remove_menu_page('edit.php?post_type=mphb_room_type');
    }
}, 999);

/* =========================================================
   EXPOSE MOTOPRESS ROOM TYPES IN ADMIN WHEN PLUGIN IS OFF
   ========================================================= */

add_action( 'init', function() {
    if ( post_type_exists( 'mphb_room_type' ) ) return;
    register_post_type( 'mphb_room_type', [
        'label'              => 'Accommodations',
        'public'             => false,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_admin_bar'  => true,
        'query_var'          => true,
        'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
    ]);
});
/**
 * Restrict menu items with class 'fbl-restricted' to admins and editors only.
 * To restrict any menu item, simply add 'fbl-restricted' to its CSS Classes
 * field in Appearance → Menus (or in the Divi menu module settings).
 */
add_filter( 'wp_nav_menu_objects', 'fbl_restrict_menu_items', 10, 2 );

function fbl_restrict_menu_items( $items, $args ) {
    // Roles allowed to see restricted items
    $allowed_roles = [ 'administrator', 'editor' ];

    // Check if current user has an allowed role
    $user = wp_get_current_user();
    $has_access = count( array_intersect( $allowed_roles, (array) $user->roles ) ) > 0;

    // If user has access, return all items unchanged
    if ( $has_access ) {
        return $items;
    }

    // Otherwise strip out any item with the fbl-restricted class
    foreach ( $items as $key => $item ) {
        if ( in_array( 'fbl-restricted', (array) $item->classes ) ) {
            unset( $items[ $key ] );
        }
    }

    return $items;
}