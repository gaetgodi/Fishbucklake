<?php
/* =========================================================
   FBL DYNAMIC BREADCRUMBS
   ========================================================= */

function fbl_breadcrumbs_output() {
    ob_start();

    echo '<div class="fbl-breadcrumb-wrapper">';
    echo '  <div class="fbl-breadcrumb-row">';
    echo '    <div class="fbl-breadcrumb-inner">';
    echo '      <div class="fbl-breadcrumb-content-wrap">';
    echo '        <div class="fbl-breadcrumb-content">';

    if (defined('FBL_BREADCRUMB_TEST_MODE') && FBL_BREADCRUMB_TEST_MODE === true) {
        echo '<a href="' . home_url() . '">Home</a> <span class="sep">›</span> ';
        echo '<a href="#">Fishing Adventures</a> <span class="sep">›</span> ';
        echo '<a href="#">Northern Ontario Region</a> <span class="sep">›</span> ';
        echo '<a href="#">Lake Systems</a> <span class="sep">›</span> ';
        echo '<span>Obakamiga (Buck) Lake Lodge Overview</span>';
    } else {
        echo '<a href="' . home_url() . '">Home</a>';

        $sep = ' <span class="sep">›</span> ';

        if (is_page() && !is_front_page()) {
            $ancestors = array_reverse(get_post_ancestors(get_the_ID()));

            foreach ($ancestors as $ancestor_id) {
                echo $sep;
                echo '<a href="' . get_permalink($ancestor_id) . '">'
                    . esc_html(get_the_title($ancestor_id)) . '</a>';
            }

            echo $sep;
            echo '<span>' . esc_html(get_the_title()) . '</span>';
        }
    }

    echo '        </div>';
    echo '        <div class="fbl-breadcrumb-line"></div>';
    echo '      </div>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('fbl_breadcrumbs', 'fbl_breadcrumbs_output');
