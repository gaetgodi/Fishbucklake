<?php
/* =========================================================
   FBL RATE ESTIMATOR - single source of truth for rates
   - wp_options row: fbl_rate_settings
   - Admin screen (Settings API, manage_options)
   - [fbl_rate_estimator] - the live calculator (moved out of
     the Divi Code module that used to live on /rez-calendar/)
   - [fbl_rate plan="cabin" item="base"] - single formatted
     figure for use inside Divi Text content (e.g. /rates/)
   ========================================================= */

/* ---------------------------------------------------------
   Defaults - match what was hardcoded in the old Divi Code
   module exactly, so nothing changes on first load.
   --------------------------------------------------------- */
function fbl_rate_settings_defaults() {
    return array(
        'tax_rate' => 13.0,
        'plans'    => array(
            'cabin' => array(
                'label'      => 'Cabin',
                'base'       => 1170.00,
                'extraAdult' => 1170.00,
                'child'      => 585.00,
                'adultBoat'  => 300.00,
                'childBoat'  => 150.00,
                'pet'        => 100.00,
            ),
            'outpost' => array(
                'label'      => 'Outpost',
                'base'       => 1180.00,
                'extraAdult' => 1180.00,
                'child'      => 590.00,
                'adultBoat'  => 0.00,
                'childBoat'  => 0.00,
                'pet'        => 100.00,
            ),
        ),
    );
}

/**
 * Single read path for every consumer (shortcodes, JS localizer,
 * admin screen). Always returns a complete, well-shaped array -
 * merges saved values over the defaults so a partially-saved
 * option (or a fresh install) never produces missing keys.
 */
function fbl_get_rate_settings() {
    $defaults = fbl_rate_settings_defaults();
    $saved    = get_option('fbl_rate_settings', array());

    if (!is_array($saved)) $saved = array();

    // Validate on read too, not just on save: register_setting()'s
    // sanitize_callback only runs when the option is written through
    // options.php (the admin form). A direct update_option() / wp-cli
    // `option update` / raw SQL write bypasses it entirely, so a bad
    // value already in the DB must not silently reach the front end -
    // fall back to the built-in default for that one field instead.
    $out = $defaults;

    if (isset($saved['tax_rate']) && is_numeric($saved['tax_rate']) && (float) $saved['tax_rate'] >= 0 && (float) $saved['tax_rate'] <= 100) {
        $out['tax_rate'] = (float) $saved['tax_rate'];
    }

    foreach ($defaults['plans'] as $plan_key => $plan_defaults) {
        if (!isset($saved['plans'][$plan_key]) || !is_array($saved['plans'][$plan_key])) continue;
        foreach ($plan_defaults as $field => $default_val) {
            if ($field === 'label') continue; // labels aren't editable via the admin screen
            if (!isset($saved['plans'][$plan_key][$field])) continue;

            $val = $saved['plans'][$plan_key][$field];
            if (is_numeric($val) && (float) $val >= 0) {
                $out['plans'][$plan_key][$field] = (float) $val;
            }
            // else: leave $out at the built-in default for this field
        }
    }

    return $out;
}

/* ---------------------------------------------------------
   Settings API registration - creates the option with
   defaults on first save; fbl_get_rate_settings() above
   covers the "never saved yet" case for reads before that.
   --------------------------------------------------------- */
add_action('admin_init', function() {
    register_setting(
        'fbl_rate_settings_group',
        'fbl_rate_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'fbl_sanitize_rate_settings',
            'default'           => fbl_rate_settings_defaults(),
        )
    );

    add_settings_section(
        'fbl_rate_section_tax',
        'Tax',
        function() { echo '<p>Applied to the pre-tax total in the rate estimator.</p>'; },
        'fbl-rates'
    );

    add_settings_field(
        'fbl_tax_rate',
        'Tax rate (%)',
        function() {
            $s = fbl_get_rate_settings();
            printf(
                '<input type="number" step="0.01" min="0" max="100" name="fbl_rate_settings[tax_rate]" value="%s" class="small-text" required> %%',
                esc_attr($s['tax_rate'])
            );
        },
        'fbl-rates',
        'fbl_rate_section_tax'
    );

    $field_labels = array(
        'base'       => 'Base rate (1 adult, per week)',
        'extraAdult' => 'Each extra adult',
        'child'      => 'Each child',
        'adultBoat'  => 'Premium boat - per adult',
        'childBoat'  => 'Premium boat - per child',
        'pet'        => 'Per pet',
    );

    foreach (fbl_rate_settings_defaults()['plans'] as $plan_key => $plan) {
        add_settings_section(
            'fbl_rate_section_' . $plan_key,
            $plan['label'] . ' plan rates (USD)',
            '__return_false',
            'fbl-rates'
        );

        foreach ($field_labels as $field => $label) {
            add_settings_field(
                'fbl_rate_' . $plan_key . '_' . $field,
                $label,
                function() use ($plan_key, $field) {
                    $s = fbl_get_rate_settings();
                    printf(
                        '<input type="number" step="0.01" min="0" name="fbl_rate_settings[plans][%s][%s]" value="%s" class="regular-text" required>',
                        esc_attr($plan_key),
                        esc_attr($field),
                        esc_attr($s['plans'][$plan_key][$field])
                    );
                },
                'fbl-rates',
                'fbl_rate_section_' . $plan_key
            );
        }
    }
});

/**
 * Sanitize + validate. Non-numeric or negative input is rejected
 * field-by-field: on a bad field we keep the previously saved
 * value for that field (never silently zero it out) and surface
 * a settings error naming the field.
 */
function fbl_sanitize_rate_settings($input) {
    $current = fbl_get_rate_settings();
    $out     = $current;

    if (isset($input['tax_rate'])) {
        if (is_numeric($input['tax_rate']) && (float) $input['tax_rate'] >= 0 && (float) $input['tax_rate'] <= 100) {
            $out['tax_rate'] = round((float) $input['tax_rate'], 2);
        } else {
            add_settings_error('fbl_rate_settings', 'fbl_tax_rate_invalid', 'Tax rate must be a number between 0 and 100. Previous value kept.');
        }
    }

    if (isset($input['plans']) && is_array($input['plans'])) {
        foreach ($current['plans'] as $plan_key => $plan) {
            if (!isset($input['plans'][$plan_key]) || !is_array($input['plans'][$plan_key])) continue;

            foreach ($plan as $field => $current_val) {
                if ($field === 'label') continue;
                if (!isset($input['plans'][$plan_key][$field])) continue;

                $val = $input['plans'][$plan_key][$field];
                if (is_numeric($val) && (float) $val >= 0) {
                    $out['plans'][$plan_key][$field] = round((float) $val, 2);
                } else {
                    add_settings_error(
                        'fbl_rate_settings',
                        'fbl_rate_' . $plan_key . '_' . $field . '_invalid',
                        ucfirst($plan_key) . ' "' . $field . '" must be a non-negative number. Previous value kept.'
                    );
                }
            }
        }
    }

    return $out;
}

/* ---------------------------------------------------------
   Admin menu - mirrors inc/catch-of-day.php's convention
   (top-level add_menu_page, dashicons, positioned in the
   same cluster the client already uses), but capability-
   gated to manage_options per spec since this is pricing.
   --------------------------------------------------------- */
add_action('admin_menu', function() {
    add_menu_page(
        'Rate Settings',
        'Rates',
        'manage_options',
        'fbl-rates',
        'fbl_rates_admin_page',
        'dashicons-money-alt',
        32
    );
});

function fbl_rates_admin_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>Rate Estimator Settings</h1>
        <p>These figures drive the Rate Estimator on the booking page and the <code>[fbl_rate]</code> figures on the Rates page. Changes apply immediately.</p>
        <form method="post" action="options.php">
            <?php
            settings_fields('fbl_rate_settings_group');
            do_settings_sections('fbl-rates');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/* ---------------------------------------------------------
   Assets - enqueued only on pages carrying the shortcode,
   matching the fbl_gallery pattern in inc/enqueue.php.
   --------------------------------------------------------- */
add_action('wp_enqueue_scripts', function() {
    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return;

    global $post;
    if (!is_a($post, 'WP_Post')) return;

    $has_estimator = has_shortcode($post->post_content, 'fbl_rate_estimator');
    $has_rate_fig  = has_shortcode($post->post_content, 'fbl_rate');

    if (!$has_estimator && !$has_rate_fig) return;

    wp_enqueue_style(
        'fbl-rate-estimator',
        get_stylesheet_directory_uri() . '/css/11-rate-estimator.css',
        array('fbl-pages'),
        filemtime(get_stylesheet_directory() . '/css/11-rate-estimator.css')
    );

    // Currency converter attaches to both the estimator and bare
    // [fbl_rate] figures - see inc/currency-converter.php. Enqueued
    // first so it can be declared as a script dependency below
    // (WP resolves the print order from the dependency graph, not
    // call order, but the handle must exist by then).
    fbl_enqueue_currency_converter();

    if ($has_estimator) {
        wp_enqueue_script(
            'fbl-rate-estimator',
            get_stylesheet_directory_uri() . '/js/rate-estimator.js',
            array('fbl-currency-converter'),
            filemtime(get_stylesheet_directory() . '/js/rate-estimator.js'),
            true
        );

        $settings = fbl_get_rate_settings();
        wp_localize_script('fbl-rate-estimator', 'fblRateData', array(
            'rates'   => $settings['plans'],
            'taxRate' => $settings['tax_rate'] / 100,
        ));
    }
}, 20);

/* ---------------------------------------------------------
   [fbl_rate_estimator]
   Markup only - same element IDs as the original Divi Code
   module so existing CSS/behaviour expectations hold.
   --------------------------------------------------------- */
add_shortcode('fbl_rate_estimator', function() {
    ob_start();
    ?>
    <div class="fbl-calc-wrap">

      <div class="fbl-calc-header">
        <h2>Rate Estimator</h2>
        <p>Weekly stays &middot; 7 nights</p>
        <p>When ready, use these values in your booking</p>
      </div>

      <div class="fbl-plan-toggle">
        <button id="fbl-btn-boat" class="active" onclick="fblSetPlan('cabin')">&#x1F6E5; Cabin With Boat</button>
        <button id="fbl-btn-outpost" onclick="fblSetPlan('outpost')">&#x1F332; Outpost</button>
      </div>

      <div class="fbl-inputs">

        <div class="fbl-field">
          <label>Adults <span class="fbl-rate-hint" id="fbl-hint-adults"></span></label>
          <select id="fbl-adults">
            <option value="1">1</option>
            <option value="2" selected>2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6</option>
            <option value="7">7</option>
            <option value="8">8</option>
          </select>
        </div>

        <div class="fbl-field">
          <label>Children <span class="fbl-rate-hint" id="fbl-hint-children"></span></label>
          <select id="fbl-children">
            <option value="0">0</option>
            <option value="1" selected>1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
          </select>
        </div>

        <div class="fbl-premium-row" id="fbl-premium-row">
          <div class="fbl-premium-label">
            Premium Boat Upgrade
            <span>Adds per-person surcharge for adults &amp; children</span>
          </div>
          <label class="fbl-toggle">
            <input type="checkbox" id="fbl-premium" onchange="fblTogglePremium()">
            <span class="fbl-toggle-track"></span>
          </label>
        </div>

        <div class="fbl-field" id="fbl-field-adultboat">
          <label>/Adult Premium Boat <span class="fbl-rate-hint" id="fbl-hint-adultboat"></span></label>
          <select id="fbl-adultboat" onchange="fblCalc()">
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6</option>
            <option value="7">7</option>
            <option value="8">8</option>
          </select>
        </div>

        <div class="fbl-field" id="fbl-field-childboat">
          <label>/Child Premium Boat <span class="fbl-rate-hint" id="fbl-hint-childboat"></span></label>
          <select id="fbl-childboat" onchange="fblCalc()">
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
          </select>
        </div>

        <div class="fbl-field">
          <label>Pets <span class="fbl-rate-hint" id="fbl-hint-pet"></span></label>
          <select id="fbl-pet" onchange="fblCalc()">
            <option value="0" selected>0</option>
            <option value="1">1</option>
            <option value="2">2</option>
          </select>
        </div>

      </div>

      <hr class="fbl-divider">

      <div class="fbl-breakdown">
        <div class="fbl-breakdown-title">Weekly Rate Breakdown</div>
        <div id="fbl-lines"></div>
      </div>

      <div class="fbl-totals">
        <div class="fbl-total-cell">
          <div class="fbl-total-label">Pre-Tax</div>
          <div class="fbl-total-amount" id="fbl-pretax">&mdash;</div>
        </div>
        <div class="fbl-total-cell">
          <div class="fbl-total-label">Tax (<?php echo esc_html(fbl_get_rate_settings()['tax_rate']); ?>%)</div>
          <div class="fbl-total-amount" id="fbl-tax">&mdash;</div>
        </div>
        <div class="fbl-total-cell fbl-grand">
          <div class="fbl-total-label">Total</div>
          <div class="fbl-total-amount" id="fbl-total">&mdash;</div>
        </div>
      </div>

      <div class="fbl-fx-row" id="fbl-fx-row" hidden></div>

      <p class="fbl-note">
        This estimate is for one cabin, one week (7 nights).<br>
        Final pricing confirmed at booking.<br>
        <a href="https://guest.rezstream.com/search/buck-lake-lodges" target="_blank" rel="noopener noreferrer">Ready to book? Check availability &rarr;</a>
      </p>

    </div>
    <?php
    return ob_get_clean();
});

/* ---------------------------------------------------------
   [fbl_rate plan="cabin" item="base"]
   Single formatted figure for Divi Text content (e.g. the
   /rates/ page) so the prose stays editable there while the
   number itself comes from the single source of truth.
   ========================================================= */
add_shortcode('fbl_rate', function($atts) {
    $atts = shortcode_atts(array(
        'plan' => 'cabin',
        'item' => 'base',
    ), $atts, 'fbl_rate');

    $settings = fbl_get_rate_settings();
    $plan     = $atts['plan'];
    $item     = $atts['item'];

    if ($item === 'tax') {
        $value = $settings['tax_rate'];
        return '<span class="fbl-rate-figure">' . esc_html(number_format($value, 0)) . '%</span>';
    }

    if (!isset($settings['plans'][$plan])) {
        return ''; // unknown plan - fail quietly rather than print a wrong number
    }

    // "premiumBase" is not a stored field - it's the standard base rate
    // plus one premium-boat surcharge, i.e. the figure the /rates/ page
    // has always shown for its "Premium Boat Package" row. Computed here
    // so it stays in sync with base/adultBoat automatically; deliberately
    // not a persisted schema field since it isn't an independent price.
    if ($item === 'premiumBase') {
        $p = $settings['plans'][$plan];
        $value = $p['base'] + $p['adultBoat'];
    } elseif (isset($settings['plans'][$plan][$item])) {
        $value = $settings['plans'][$plan][$item];
    } else {
        return ''; // unknown item - fail quietly rather than print a wrong number
    }

    return sprintf(
        '<span class="fbl-price" data-fbl-usd="%s">$%s <span class="fbl-price-fx"></span></span>',
        esc_attr(number_format($value, 2, '.', '')),
        esc_html(number_format($value, 2))
    );
});
