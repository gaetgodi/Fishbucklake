add_action('wp_footer', function() {
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return;

    echo '
    <button id="fbl-mm-open" aria-label="Open navigation menu">
        <span class="fbl-mm-open-icon"></span>
    </button>

    <div id="fbl-mm-overlay"></div>

    <div id="fbl-mm-bottom-sheet">
        <div class="fbl-mm-sheet-header">
            <span class="fbl-mm-sheet-title">Navigation</span>
            <button id="fbl-mm-close" aria-label="Close menu">
                <span id="fbl-mm-close-icon"></span>
            </button>
        </div>
        <div class="fbl-mm-sheet-body">
            ' . do_shortcode('[fbl_mobile_menu]') . '
            ' . do_shortcode('[fbl_footer_mobile_menu]') . '
        </div>
    </div>';
});