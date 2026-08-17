<?php
/* =========================================================
   FBL CURRENCY CONVERTER
   Base currency USD. Fetches CAD/EUR/GBP/CNY/JPY once a day
   server-side (frankfurter.app, no API key), caches in a
   transient following the shape used in inc/gallery.php
   (prefixed key, HOUR_IN_SECONDS multiple, get -> fetch -> set).
   Conversion itself happens client-side from the cached table -
   no per-visitor API calls.
   ========================================================= */

define('FBL_FX_TRANSIENT', 'fbl_fx_rates');
define('FBL_FX_CURRENCIES', array('CAD', 'EUR', 'GBP', 'CNY', 'JPY'));

/**
 * Fetch fresh rates from frankfurter.app and cache them.
 * On failure, the existing transient is left untouched -
 * callers keep serving the last known-good rates rather than
 * losing them. Returns the cached array (fresh or stale) or
 * false if nothing has ever been fetched successfully.
 */
function fbl_fetch_fx_rates() {
    $url = add_query_arg(
        array('from' => 'USD', 'to' => implode(',', FBL_FX_CURRENCIES)),
        'https://api.frankfurter.app/latest'
    );

    $response = wp_remote_get($url, array('timeout' => 8));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return get_transient(FBL_FX_TRANSIENT); // leave any existing cache alone
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['rates']) || empty($body['date'])) {
        return get_transient(FBL_FX_TRANSIENT);
    }

    $data = array(
        'date'  => sanitize_text_field($body['date']), // API's rate date, not our fetch time
        'base'  => 'USD',
        'rates' => array_map('floatval', $body['rates']),
    );

    set_transient(FBL_FX_TRANSIENT, $data, 24 * HOUR_IN_SECONDS);

    return $data;
}

/**
 * Read path for every consumer. Never fabricates a value: if
 * nothing has ever been cached, returns false and callers must
 * hide the converter rather than render a broken control.
 */
function fbl_get_fx_rates() {
    $cached = get_transient(FBL_FX_TRANSIENT);
    if ($cached !== false) return $cached;

    // Transient expired (or first run) - try a synchronous refetch
    // so a visitor isn't stuck without rates until the next cron
    // tick; on failure this returns false and the converter hides.
    return fbl_fetch_fx_rates();
}

/* ---------------------------------------------------------
   Daily cron - self-schedules on 'init' if not already
   scheduled, so no separate activation hook is required.
   --------------------------------------------------------- */
add_action('init', function() {
    if (!wp_next_scheduled('fbl_fetch_fx_rates_event')) {
        wp_schedule_event(time(), 'daily', 'fbl_fetch_fx_rates_event');
    }
});

add_action('fbl_fetch_fx_rates_event', 'fbl_fetch_fx_rates');

/* ---------------------------------------------------------
   Assets - called from inc/rate-estimator.php's shortcode
   enqueue block (only when [fbl_rate_estimator] or [fbl_rate]
   is present on the page). Renders nothing itself; the JS
   builds the <select> and finds .fbl-price elements to convert.
   --------------------------------------------------------- */
function fbl_enqueue_currency_converter() {
    if (wp_script_is('fbl-currency-converter', 'enqueued')) return; // avoid double-localize if called twice
    $fx = fbl_get_fx_rates();

    wp_enqueue_script(
        'fbl-currency-converter',
        get_stylesheet_directory_uri() . '/js/currency-converter.js',
        array(),
        filemtime(get_stylesheet_directory() . '/js/currency-converter.js'),
        true
    );

    wp_localize_script('fbl-currency-converter', 'fblFxData', array(
        'available' => $fx !== false,
        'date'      => $fx ? $fx['date'] : '',
        'rates'     => $fx ? $fx['rates'] : new stdClass(),
    ));
}
