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
        'tax_rate'     => 13.0,
        'deposit_rate' => 15.0, // matches the "15% of the total price" hardcoded on /rates/ and /rez-calendar/
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

        // Verbatim from the /rates/ page's hardcoded Divi Code module,
        // as of the boat-description editability change - so nothing
        // changes on first load. "standard" and "premium" each held a
        // single <li>; "outpost" held two (boat + motor), concatenated
        // here into one per the client's go-ahead so the schema stays
        // one field per plan section.
        'boat_descriptions' => array(
            'standard' => 'Well maintained, 14 ft aluminum boat with 15 hp Yamaha electric start motor, swivel seats, depth finder, landing net, bait bucket, safety kit, and life preservers',
            'premium'  => "16' Mirrocraft boat with 30hp Yamaha electric start motor with power tilt, RPM speed control, live well, rod storage, pedestal bucket seats, flat bottom floors, depth finder, landing net, bait bucket, safety kit and life preservers",
            'outpost'  => "14' Lund boats with swivel seats, landing nets, bait buckets, paddle and safety kit, powered by 9.9HP or 15HP motors",
        ),
    );
}

/**
 * Formatting allowed in the boat-description textareas: enough to bold
 * or italicize a phrase or break a line, nothing that could inject a
 * link, script, or block-level markup into the middle of Divi Code
 * module content. Shared between the sanitize callback (write) and
 * fbl_get_rate_settings() (read, for values that reached the DB some
 * other way).
 */
function fbl_boat_description_allowed_tags() {
    return array(
        'strong' => array(),
        'b'      => array(),
        'em'     => array(),
        'i'      => array(),
        'br'     => array(),
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

    if (isset($saved['deposit_rate']) && is_numeric($saved['deposit_rate']) && (float) $saved['deposit_rate'] >= 0 && (float) $saved['deposit_rate'] <= 100) {
        $out['deposit_rate'] = (float) $saved['deposit_rate'];
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

    if (isset($saved['boat_descriptions']) && is_array($saved['boat_descriptions'])) {
        foreach ($defaults['boat_descriptions'] as $key => $default_text) {
            if (!isset($saved['boat_descriptions'][$key]) || !is_string($saved['boat_descriptions'][$key])) continue;

            $clean = trim(wp_kses($saved['boat_descriptions'][$key], fbl_boat_description_allowed_tags()));
            if ($clean !== '') {
                $out['boat_descriptions'][$key] = $clean;
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
        'Tax & Deposit',
        function() { echo '<p>Tax is applied to the pre-tax total in the rate estimator. Deposit is a percentage of the total price, quoted via <code>[fbl_rate item="deposit"]</code> on the Rates and Booking pages - it is not itself a dollar figure, so it isn\'t affected by the currency converter.</p>'; },
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

    add_settings_field(
        'fbl_deposit_rate',
        'Deposit rate (%)',
        function() {
            $s = fbl_get_rate_settings();
            printf(
                '<input type="number" step="0.01" min="0" max="100" name="fbl_rate_settings[deposit_rate]" value="%s" class="small-text" required> %%',
                esc_attr($s['deposit_rate'])
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

    add_settings_section(
        'fbl_rate_section_boat_descriptions',
        'Boat Descriptions',
        function() { echo '<p>Shown on the Rates page via <code>[fbl_boat_description plan="standard|premium|outpost"]</code>. Basic formatting only: <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;br&gt;</code> - everything else (including links) is stripped on save.</p>'; },
        'fbl-rates'
    );

    $boat_description_labels = array(
        'standard' => 'Standard Boat Package',
        'premium'  => 'Premium Boat Package',
        'outpost'  => 'Outpost',
    );

    foreach ($boat_description_labels as $key => $label) {
        add_settings_field(
            'fbl_boat_description_' . $key,
            $label,
            function() use ($key) {
                $s = fbl_get_rate_settings();
                printf(
                    '<textarea name="fbl_rate_settings[boat_descriptions][%s]" rows="3" class="large-text" required>%s</textarea>',
                    esc_attr($key),
                    esc_textarea($s['boat_descriptions'][$key])
                );
            },
            'fbl-rates',
            'fbl_rate_section_boat_descriptions'
        );
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

    if (isset($input['deposit_rate'])) {
        if (is_numeric($input['deposit_rate']) && (float) $input['deposit_rate'] >= 0 && (float) $input['deposit_rate'] <= 100) {
            $out['deposit_rate'] = round((float) $input['deposit_rate'], 2);
        } else {
            add_settings_error('fbl_rate_settings', 'fbl_deposit_rate_invalid', 'Deposit rate must be a number between 0 and 100. Previous value kept.');
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

    if (isset($input['boat_descriptions']) && is_array($input['boat_descriptions'])) {
        foreach ($current['boat_descriptions'] as $key => $current_text) {
            if (!isset($input['boat_descriptions'][$key])) continue;

            // options.php hands the sanitize_callback the raw, magic-quote
            // -slashed $_POST value (WP does not unslash before calling
            // it) - wp_unslash() first or an apostrophe like "14' Lund"
            // gets a literal backslash baked into the stored option.
            $raw   = wp_unslash($input['boat_descriptions'][$key]);
            $clean = trim(wp_kses($raw, fbl_boat_description_allowed_tags()));

            if ($clean !== '') {
                $out['boat_descriptions'][$key] = $clean;
            } else {
                add_settings_error(
                    'fbl_rate_settings',
                    'fbl_boat_description_' . $key . '_invalid',
                    ucfirst($key) . ' boat description cannot be empty. Previous value kept.'
                );
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
    $hook = add_menu_page(
        'Rate Settings',
        'Rates',
        'manage_options',
        'fbl-rates',
        'fbl_rates_admin_page',
        'dashicons-money-alt',
        32
    );

    // Stash the real hook suffix rather than guessing
    // 'toplevel_page_fbl-rates' - used to gate the preview's
    // assets to just this one admin screen.
    add_action('load-' . $hook, function() {
        add_action('admin_enqueue_scripts', 'fbl_enqueue_rates_admin_preview_assets');
    });
});

function fbl_enqueue_rates_admin_preview_assets() {
    wp_enqueue_style(
        'fbl-rate-estimator',
        get_stylesheet_directory_uri() . '/css/11-rate-estimator.css',
        array(),
        filemtime(get_stylesheet_directory() . '/css/11-rate-estimator.css')
    );

    // Same handle, same file, same fblRateData shape as the front end -
    // this is the live shortcode's own JS, not a reimplementation. No
    // currency-converter dependency here: the admin preview is USD-only,
    // and rate-estimator.js already no-ops the fx hook when it's absent.
    wp_enqueue_script(
        'fbl-rate-estimator',
        get_stylesheet_directory_uri() . '/js/rate-estimator.js',
        array(),
        filemtime(get_stylesheet_directory() . '/js/rate-estimator.js'),
        true
    );

    $settings = fbl_get_rate_settings();
    wp_localize_script('fbl-rate-estimator', 'fblRateData', array(
        'rates'   => $settings['plans'],
        'taxRate' => $settings['tax_rate'] / 100,
    ));

    // Preset a realistic booking (2 adults, 1 child, premium boat, 1 pet)
    // by driving the same controls a visitor would click - not a second
    // calculation path, just simulated input on the real widget.
    wp_add_inline_script('fbl-rate-estimator', "
        document.addEventListener('DOMContentLoaded', function () {
            var pet = document.getElementById('fbl-pet');
            var premium = document.getElementById('fbl-premium');
            if (!pet || !premium) return;
            pet.value = '1';
            premium.checked = true;
            fblTogglePremium();
        });
    ");
}

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

        <hr style="margin: 2rem 0;">

        <h2>Live Preview &mdash; Saved Rates</h2>
        <p><strong>This preview reflects your saved rates, not what's in the form above.</strong> It updates only after you click "Save Changes" and the page reloads - it is not a live-as-you-type calculator, and there is no separate draft version of your rates: what's shown here is exactly what visitors see right now on the booking page.</p>
        <p>Preset to a sample booking (2 adults, 1 child, premium boat, 1 pet) so you can see the effect of a rate change at a glance. Feel free to change the fields below too - it's the same live estimator that's on the booking page.</p>
        <div style="max-width: 720px;">
            <?php echo do_shortcode('[fbl_rate_estimator]'); ?>
        </div>
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
    <div class="fbl-calc-wrap" id="fbl-estimate">

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

    if ($item === 'deposit') {
        // Percentage of the (variable) total price, not a dollar figure -
        // no data-fbl-usd here, same treatment as tax/childPercent above,
        // so the currency converter correctly leaves it alone.
        $value = $settings['deposit_rate'];
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
    } elseif ($item === 'childPercent') {
        // Derived like premiumBase above - not a persisted field. Used
        // for the "children are charged at X% of the adult rate" policy
        // copy on /rates/ and /rez-calendar/, which was previously a
        // hardcoded "50%"/"half" independent of the child/base fields
        // it actually describes.
        $p = $settings['plans'][$plan];
        $value = $p['base'] > 0 ? round(($p['child'] / $p['base']) * 100) : 0;
        return '<span class="fbl-rate-figure">' . esc_html($value) . '%</span>';
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

/* ---------------------------------------------------------
   [fbl_boat_description plan="standard|premium|outpost"]
   Editable replacement for the boat spec text that used to be
   hardcoded inside the /rates/ page's Divi Code module. The
   value returned is already wp_kses()'d - on save by
   fbl_sanitize_rate_settings() and again on read by
   fbl_get_rate_settings() - to a small allow-list (see
   fbl_boat_description_allowed_tags()), so it's safe to print
   as-is; escaping it again here would mangle the allowed tags.
   ========================================================= */
add_shortcode('fbl_boat_description', function($atts) {
    $atts = shortcode_atts(array(
        'plan' => 'standard',
    ), $atts, 'fbl_boat_description');

    $settings = fbl_get_rate_settings();
    $plan     = $atts['plan'];

    if (!isset($settings['boat_descriptions'][$plan])) {
        return ''; // unknown plan - fail quietly rather than print nothing useful
    }

    return $settings['boat_descriptions'][$plan];
});
