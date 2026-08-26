<?php
/* =========================================================
   FBL RATE ESTIMATOR - single source of truth for rates
   - wp_options row: fbl_rate_settings
   - Admin screen (Settings API, manage_fbl_rates)
   - [fbl_rate_estimator] - the live calculator (moved out of
     the Divi Code module that used to live on /rez-calendar/)
   - [fbl_rate plan="cabin" item="base"] - single formatted
     figure for use inside Divi Text content (e.g. /rates/)
   ========================================================= */

/* ---------------------------------------------------------
   Capabilities (mirrors catch-of-day / gallery-builder pattern -
   grant a feature-specific cap to the built-in editor role
   rather than gating on manage_options, so Shannon/Nev's
   existing editor accounts get this screen like the others)
   --------------------------------------------------------- */
add_action('admin_init', function() {
    $editor = get_role('editor');
    $admin  = get_role('administrator');

    if ($editor) $editor->add_cap('manage_fbl_rates');
    if ($admin)  $admin->add_cap('manage_fbl_rates');
});

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
            'standard' => 'Well maintained, 16 ft aluminum boat with 20 hp Yamaha electric start motor, swivel seats, depth finder, landing net, bait bucket, safety kit, and life preservers',
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

/**
 * Single read path for the brochure PDF, mirroring
 * fbl_get_rate_settings() above: re-validates on every read (not just
 * on save) so a removed/retyped attachment, or a raw option written
 * outside the settings form, can never silently point at a dead file.
 * Returns 0 (falsy) whenever there's no usable PDF - both
 * [fbl_brochure_form] and fbl-contact-system.php's send_brochure_reply()
 * treat that as "no PDF configured yet" and fall back accordingly.
 */
function fbl_get_brochure_pdf_id() {
    $id = (int) get_option('fbl_brochure_pdf_id', 0);

    if ($id <= 0) return 0;
    if (get_post_type($id) !== 'attachment') return 0;
    if (get_post_mime_type($id) !== 'application/pdf') return 0;

    $file = get_attached_file($id);
    if (!$file || !file_exists($file)) return 0;

    return $id;
}

/**
 * Whether FAQ search Demo Mode is currently on. Read by
 * fbl-faq-search-system.php (mu-plugin, deployed separately - see that
 * file's header) to decide the per-visitor rate limit: 30/10min instead
 * of the normal 5/10min while this is enabled. Lives as its own option
 * rather than a fbl_rate_settings key since it's a live on/off switch,
 * not a saved rate figure.
 */
function fbl_faqsearch_demo_mode_enabled() {
    return (bool) get_option('fbl_faqsearch_demo_mode', false);
}

/* ---------------------------------------------------------
   Settings API registration - creates the option with
   defaults on first save; fbl_get_rate_settings() above
   covers the "never saved yet" case for reads before that.
   --------------------------------------------------------- */
add_action('admin_init', function() {
    // options.php enforces manage_options on every settings-group submit
    // unless this filter says otherwise - has to be set alongside
    // register_setting() or the form 403s for anyone without manage_options.
    add_filter('option_page_capability_fbl_rate_settings_group', function() {
        return 'manage_fbl_rates';
    });

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

    /* -----------------------------------------------------
       Brochure Copy - fbl_brochure_copy option, read via
       fbl_get_brochure_copy() (inc/brochure-generator.php). Same
       "own register_setting() under the same settings group" pattern
       as fbl_brochure_pdf_id below: not a rate figure, needs its own
       defaults/sanitize path, but still saves through this one
       screen/button. Split into sections that mirror the brochure's
       own layout (header, highlights, CTA/closing, footer) purely for
       readability here - fbl_get_brochure_copy() itself treats it as
       one flat array.
       ----------------------------------------------------- */
    register_setting(
        'fbl_rate_settings_group',
        'fbl_brochure_copy',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'fbl_sanitize_brochure_copy',
            'default'           => fbl_get_brochure_copy(),
        )
    );

    add_settings_section(
        'fbl_rate_section_brochure_copy_header',
        'Brochure Copy &mdash; Header',
        function() { echo '<p>Copy for the brochure PDF (built by the Brochure PDF field further down). Save changes here first, then use "Generate PDF" below to rebuild the file from them.</p>'; },
        'fbl-rates'
    );

    fbl_add_brochure_copy_fields(array(
        'eyebrow'    => array('Eyebrow', 'text'),
        'title'      => array('Title', 'text'),
        'header_sub' => array('Header subtitle', 'text'),
        'lede'       => array('Intro paragraph', 'richtext'),
    ), 'fbl_rate_section_brochure_copy_header');

    add_settings_section(
        'fbl_rate_section_brochure_copy_highlights',
        'Brochure Copy &mdash; Highlights',
        '__return_false',
        'fbl-rates'
    );

    fbl_add_brochure_copy_fields(array(
        'stat1_num'    => array('Stat 1 number', 'text'),
        'stat1_label'  => array('Stat 1 label', 'text'),
        'stat2_num'    => array('Stat 2 number', 'text'),
        'stat2_label'  => array('Stat 2 label', 'text'),
        'stat3_num'    => array('Stat 3 number', 'text'),
        'stat3_label'  => array('Stat 3 label', 'text'),
        'species_line' => array('Species line', 'richtext'),
    ), 'fbl_rate_section_brochure_copy_highlights');

    add_settings_section(
        'fbl_rate_section_brochure_copy_cta',
        'Brochure Copy &mdash; Call to Action &amp; Closing',
        '__return_false',
        'fbl-rates'
    );

    fbl_add_brochure_copy_fields(array(
        'cta_headline' => array('CTA headline', 'text'),
        'cta_sub'      => array('CTA subtext', 'text'),
        'cta_url'      => array('CTA URL (display text)', 'text'),
        'closing'      => array('Closing line', 'text'),
    ), 'fbl_rate_section_brochure_copy_cta');

    add_settings_section(
        'fbl_rate_section_brochure_copy_footer',
        'Brochure Copy &mdash; Footer',
        function() { echo '<p>The footer QR code is generated automatically from the URL below at PDF build time - no image upload needed.</p>'; },
        'fbl-rates'
    );

    fbl_add_brochure_copy_fields(array(
        'footer_business'   => array('Business name', 'text'),
        'footer_address'    => array('Address', 'text'),
        'footer_phone'      => array('Phone', 'text'),
        'footer_email'      => array('Email', 'text'),
        'footer_qr_url'     => array('QR code URL', 'url'),
        'footer_qr_caption' => array('QR code caption', 'text'),
    ), 'fbl_rate_section_brochure_copy_footer');

    /* -----------------------------------------------------
       Brochure PDF - a separate option (fbl_brochure_pdf_id),
       not a key on fbl_rate_settings: it's not a rate figure,
       doesn't belong in the fblRateData JS payload, and needs
       attachment-type validation rather than the numeric/text
       checks the rest of this file's sanitize callback does.
       Still on this same screen/capability/save button via a
       second register_setting() under the same settings group.
       ----------------------------------------------------- */
    register_setting(
        'fbl_rate_settings_group',
        'fbl_brochure_pdf_id',
        array(
            'type'              => 'integer',
            'sanitize_callback' => 'fbl_sanitize_brochure_pdf_id',
            'default'           => 0,
        )
    );

    add_settings_section(
        'fbl_rate_section_brochure',
        'Brochure PDF',
        function() { echo '<p>Sent as an email attachment to visitors who request it via <code>[fbl_brochure_form]</code> on the Brochure page. Until a PDF is uploaded here, that page shows a "coming soon" message linking to Contact Us instead of a request form.</p>'; },
        'fbl-rates'
    );

    add_settings_field(
        'fbl_brochure_pdf_id',
        'Brochure PDF',
        'fbl_render_brochure_pdf_field',
        'fbl-rates',
        'fbl_rate_section_brochure'
    );

    /* -----------------------------------------------------
       FAQ Search Demo Mode - a separate option (fbl_faqsearch_demo_mode),
       same reasoning as the brochure PDF above: not a rate figure, on
       its own register_setting() under the same settings group so it
       still saves via this one screen/button. Read by
       fbl-faq-search-system.php (mu-plugin) to raise the per-visitor
       rate limit while showing the search box to someone live.
       ----------------------------------------------------- */
    register_setting(
        'fbl_rate_settings_group',
        'fbl_faqsearch_demo_mode',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => function($input) { return !empty($input); },
            'default'           => false,
        )
    );

    add_settings_section(
        'fbl_rate_section_faqsearch_demo',
        'FAQ Search Demo Mode',
        '__return_false',
        'fbl-rates'
    );

    add_settings_field(
        'fbl_faqsearch_demo_mode',
        'Demo Mode',
        function() {
            $checked = fbl_faqsearch_demo_mode_enabled();
            ?>
            <label>
                <input type="checkbox" name="fbl_faqsearch_demo_mode" value="1" <?php checked($checked); ?>>
                Enable Demo Mode
            </label>
            <p class="description" style="max-width: 640px;">
                Demo Mode temporarily raises the FAQ search's per-visitor limit from 5 to 30
                questions every 10 minutes. Turn it on when you're showing the search box to
                someone in person, so a normal demo doesn't get cut off by the usual limit.
                Remember to switch it back off when you're done &mdash; it's meant for demos,
                not everyday browsing.
            </p>
            <?php
        },
        'fbl-rates',
        'fbl_rate_section_faqsearch_demo'
    );
});

/**
 * Renders the brochure PDF media-uploader field. A standard
 * wp.media() attachment picker restricted to application/pdf, backed
 * by a hidden input carrying the attachment ID - the only thing this
 * settings field actually saves.
 */
function fbl_render_brochure_pdf_field() {
    $id       = fbl_get_brochure_pdf_id();
    $filename = $id ? basename(get_attached_file($id)) : '';
    $url      = $id ? wp_get_attachment_url($id) : '';
    ?>
    <input type="hidden" name="fbl_brochure_pdf_id" id="fbl_brochure_pdf_id_input" value="<?php echo esc_attr($id); ?>">

    <p id="fbl-brochure-pdf-current" <?php if (!$id) echo 'style="display:none;"'; ?>>
        Current file:
        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" id="fbl-brochure-pdf-link"><?php echo esc_html($filename); ?></a>
        &nbsp;<button type="button" class="button" id="fbl-brochure-pdf-remove">Remove</button>
    </p>
    <p id="fbl-brochure-pdf-none" <?php if ($id) echo 'style="display:none;"'; ?>>
        <em>No PDF uploaded yet.</em>
    </p>
    <button type="button" class="button" id="fbl-brochure-pdf-upload">Select PDF&hellip;</button>

    <script>
    (function () {
        var frame;
        var uploadBtn = document.getElementById('fbl-brochure-pdf-upload');
        var removeBtn = document.getElementById('fbl-brochure-pdf-remove');
        if (!uploadBtn) return;

        uploadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }

            frame = wp.media({
                title: 'Select Brochure PDF',
                library: { type: 'application/pdf' },
                button: { text: 'Use this PDF' },
                multiple: false
            });

            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                document.getElementById('fbl_brochure_pdf_id_input').value = att.id;
                document.getElementById('fbl-brochure-pdf-link').href = att.url;
                document.getElementById('fbl-brochure-pdf-link').textContent = att.filename;
                document.getElementById('fbl-brochure-pdf-current').style.display = '';
                document.getElementById('fbl-brochure-pdf-none').style.display = 'none';
            });

            frame.open();
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('fbl_brochure_pdf_id_input').value = '';
                document.getElementById('fbl-brochure-pdf-current').style.display = 'none';
                document.getElementById('fbl-brochure-pdf-none').style.display = '';
            });
        }
    })();
    </script>
    <?php
}

/**
 * Sanitize + validate the brochure PDF selection. Same "reject and
 * keep the previous value" convention as fbl_sanitize_rate_settings()
 * above, rather than silently clearing it on a bad submission.
 */
function fbl_sanitize_brochure_pdf_id($input) {
    $input = trim((string) $input);

    if ($input === '' || $input === '0') {
        return 0;
    }

    $id = absint($input);

    if ($id <= 0 || get_post_type($id) !== 'attachment') {
        add_settings_error('fbl_brochure_pdf_id', 'fbl_brochure_pdf_invalid', 'Brochure PDF selection was invalid. Previous value kept.');
        return fbl_get_brochure_pdf_id();
    }

    if (get_post_mime_type($id) !== 'application/pdf') {
        add_settings_error('fbl_brochure_pdf_id', 'fbl_brochure_pdf_not_pdf', 'Selected file must be a PDF. Previous value kept.');
        return fbl_get_brochure_pdf_id();
    }

    return $id;
}

/**
 * Registers one add_settings_field() per brochure-copy field - shared by
 * every fbl_rate_section_brochure_copy_* group above so the render
 * callback (type-aware: plain text input, URL input, or one of the two
 * kses-limited richtext textareas) is written once instead of once per
 * field.
 */
function fbl_add_brochure_copy_fields($fields, $section) {
    foreach ($fields as $key => $spec) {
        list($label, $type) = $spec;

        add_settings_field(
            'fbl_brochure_copy_' . $key,
            $label,
            function() use ($key, $type) {
                $value = fbl_get_brochure_copy()[$key];

                if ($type === 'richtext') {
                    printf(
                        '<textarea name="fbl_brochure_copy[%s]" rows="3" class="large-text" required>%s</textarea>
                         <p class="description">Basic formatting only: <code>&lt;strong&gt;</code> / <code>&lt;b&gt;</code> - everything else is stripped on save.</p>',
                        esc_attr($key),
                        esc_textarea($value)
                    );
                } elseif ($type === 'url') {
                    printf(
                        '<input type="url" name="fbl_brochure_copy[%s]" value="%s" class="regular-text" required>',
                        esc_attr($key),
                        esc_attr($value)
                    );
                } else {
                    printf(
                        '<input type="text" name="fbl_brochure_copy[%s]" value="%s" class="regular-text" required>',
                        esc_attr($key),
                        esc_attr($value)
                    );
                }
            },
            'fbl-rates',
            $section
        );
    }
}

/**
 * Sanitize + validate the brochure copy fields. Same "reject a bad
 * field and keep its previous value, never silently blank it out"
 * convention as fbl_sanitize_rate_settings() below. 'lede' and
 * 'species_line' get the same narrow wp_kses() pass
 * fbl_brochure_kses() (inc/brochure-generator.php) applies again at
 * render time - belt and suspenders, since output is already kses'd
 * there too, but there's no reason to let raw unfiltered HTML sit in
 * the option either. Everything else is plain text.
 */
function fbl_sanitize_brochure_copy($input) {
    $current = fbl_get_brochure_copy();
    $out     = $current;

    if (!is_array($input)) {
        return $out;
    }

    $richtext_fields = array('lede', 'species_line');

    foreach ($current as $key => $current_val) {
        if (!isset($input[$key])) continue;

        // options.php hands the sanitize_callback the raw, magic-quote
        // -slashed $_POST value (WP does not unslash before calling it) -
        // wp_unslash() first, same reasoning as fbl_sanitize_rate_settings()'s
        // boat_descriptions handling below.
        $raw = wp_unslash($input[$key]);

        if (in_array($key, $richtext_fields, true)) {
            $clean = trim(fbl_brochure_kses($raw));
        } elseif ($key === 'footer_qr_url') {
            $clean = esc_url_raw(trim($raw));
        } else {
            $clean = trim(sanitize_text_field($raw));
        }

        if ($clean !== '') {
            $out[$key] = $clean;
        } else {
            add_settings_error(
                'fbl_brochure_copy',
                'fbl_brochure_copy_' . $key . '_empty',
                ucwords(str_replace('_', ' ', $key)) . ' cannot be empty. Previous value kept.'
            );
        }
    }

    return $out;
}

/* ---------------------------------------------------------
   Generate PDF button - a plain admin-post.php action, not a
   Settings API field: pressing it performs a side effect (builds a
   file, writes or replaces a Media Library attachment) rather than
   just sanitizing and storing form input, so it needs its own <form>
   and handler instead of living inside the settings group above. See
   inc/brochure-generator.php's header comment for the tmp-file
   cleanup and replace-in-place contract this implements.
   --------------------------------------------------------- */
add_action('admin_post_fbl_generate_brochure_pdf', 'fbl_handle_generate_brochure_pdf');

function fbl_handle_generate_brochure_pdf() {
    if (!current_user_can('manage_fbl_rates')) {
        wp_die('You do not have permission to do this.', 403);
    }
    check_admin_referer('fbl_generate_brochure_pdf');

    $tmp_path = fbl_generate_brochure_pdf();

    if (!$tmp_path) {
        fbl_redirect_brochure_admin('fbl_brochure_generate_failed');
    }

    $existing_id = fbl_get_brochure_pdf_id();
    $result_id   = $existing_id
        ? fbl_replace_brochure_attachment($existing_id, $tmp_path)
        : fbl_insert_brochure_attachment($tmp_path);

    // Cleanup is this caller's job on every path, success or failure -
    // fbl_generate_brochure_pdf() never deletes its own tmp file (see
    // that function's header comment).
    unlink($tmp_path);

    fbl_redirect_brochure_admin($result_id ? 'fbl_brochure_generated' : 'fbl_brochure_generate_failed');
}

/**
 * Regenerate case: overwrite the existing attachment's file in place
 * and refresh its metadata, rather than inserting a new Media Library
 * row. $id is assumed already validated (it comes from
 * fbl_get_brochure_pdf_id(), which checks post type/mime/file
 * existence), so the only new failure mode here is the copy() itself.
 */
function fbl_replace_brochure_attachment($id, $tmp_path) {
    $file = get_attached_file($id);
    if (!$file || !@copy($tmp_path, $file)) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));

    return $id;
}

/**
 * First-ever generate: sideload the tmp file into the uploads
 * directory as a new attachment and point fbl_brochure_pdf_id at it.
 * Every generate after this one goes through
 * fbl_replace_brochure_attachment() above instead - see the "only the
 * very first-ever generate should insert" note in
 * inc/brochure-generator.php's header comment.
 */
function fbl_insert_brochure_attachment($tmp_path) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        return 0;
    }

    $filename = wp_unique_filename($upload_dir['path'], 'fish-buck-lake-brochure.pdf');
    $dest     = trailingslashit($upload_dir['path']) . $filename;

    if (!@copy($tmp_path, $dest)) {
        return 0;
    }

    $id = wp_insert_attachment(array(
        'post_mime_type' => 'application/pdf',
        'post_title'     => 'Fish Buck Lake Brochure',
        'post_content'   => '',
        'post_status'    => 'inherit',
    ), $dest);

    if (!$id || is_wp_error($id)) {
        @unlink($dest);
        return 0;
    }

    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $dest));
    update_option('fbl_brochure_pdf_id', $id);

    return $id;
}

/**
 * Redirects back to the Rates screen with a one-shot ?notice=1 query
 * arg for fbl_rates_admin_page() to print as an admin notice. Not the
 * Settings API's settings_errors() path (this isn't a settings save),
 * just the plain "action then redirect with a flag" convention every
 * admin-post.php handler uses.
 */
function fbl_redirect_brochure_admin($notice) {
    wp_safe_redirect(add_query_arg(array('page' => 'fbl-rates', $notice => '1'), admin_url('admin.php')));
    exit;
}

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
   same cluster the client already uses), gated to
   manage_fbl_rates so it lines up with the other custom
   editor capabilities (manage_fbl_gallery etc.) instead of
   manage_options.
   --------------------------------------------------------- */
add_action('admin_menu', function() {
    $hook = add_menu_page(
        'Rate Settings',
        'Rates',
        'manage_fbl_rates',
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
    // Backs the Brochure PDF field's media picker (fbl_render_brochure_pdf_field()).
    wp_enqueue_media();

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
        'rates'       => $settings['plans'],
        'taxRate'     => $settings['tax_rate'] / 100,
        'depositRate' => $settings['deposit_rate'] / 100,
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
    if (!current_user_can('manage_fbl_rates')) return;
    ?>
    <div class="wrap">
        <h1>Rate Estimator Settings</h1>
        <p>These figures drive the Rate Estimator on the booking page and the <code>[fbl_rate]</code> figures on the Rates page. Changes apply immediately.</p>

        <?php
        // Settings API errors/success (fbl_sanitize_rate_settings(),
        // fbl_sanitize_brochure_copy(), fbl_sanitize_brochure_pdf_id() all
        // add_settings_error() into this same queue on a bad submission).
        settings_errors();

        // Plain one-shot flags from fbl_redirect_brochure_admin() above -
        // the Generate PDF button isn't a settings save, so it doesn't go
        // through settings_errors().
        if (isset($_GET['fbl_brochure_generated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Brochure PDF generated and saved.</p></div>';
        }
        if (isset($_GET['fbl_brochure_generate_failed'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Brochure PDF generation failed. Nothing was saved - the previous PDF (if any) is still in place. Please try again.</p></div>';
        }
        ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('fbl_rate_settings_group');
            do_settings_sections('fbl-rates');
            submit_button();
            ?>
        </form>

        <h2>Generate Brochure PDF</h2>
        <p>Builds the brochure PDF from the Brochure Copy fields and the <code>Brochure_FBL</code> photo folder above, and saves it as the file selected in the Brochure PDF field above (replacing it in place if one already exists). <strong>Save your copy changes first</strong> - Generate always uses what's currently saved, not unsaved edits still sitting in the form above.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('fbl_generate_brochure_pdf'); ?>
            <input type="hidden" name="action" value="fbl_generate_brochure_pdf">
            <?php submit_button('Generate PDF', 'primary', 'submit', false); ?>
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
            'rates'       => $settings['plans'],
            'taxRate'     => $settings['tax_rate'] / 100,
            'depositRate' => $settings['deposit_rate'] / 100,
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

      <div class="fbl-deposit-row" id="fbl-deposit-row">
        <div class="fbl-deposit-line">
          <span class="fbl-deposit-label">Deposit due now (<?php echo esc_html(number_format(fbl_get_rate_settings()['deposit_rate'], 0)); ?>%)</span>
          <span id="fbl-deposit"></span>
        </div>
        <div class="fbl-deposit-line">
          <span class="fbl-deposit-label">Balance due on arrival</span>
          <span id="fbl-balance"></span>
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
        'plan'   => 'cabin',
        'item'   => 'base',
        'suffix' => '', // e.g. suffix="(USD)" - rendered inside the .fbl-price span,
                         // right after the dollar figure and before the ≈-converted
                         // .fbl-price-fx figure, so it stays attached to the source
                         // USD amount instead of ending up after the conversion.
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

    $suffix = ($atts['suffix'] !== '') ? ' ' . esc_html($atts['suffix']) : '';

    return sprintf(
        '<span class="fbl-price" data-fbl-usd="%s">$%s%s <span class="fbl-price-fx"></span></span>',
        esc_attr(number_format($value, 2, '.', '')),
        esc_html(number_format($value, 2)),
        $suffix
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

/* ---------------------------------------------------------
   [fbl_brochure_form]
   Renders the brochure request form (name + email, posts to the
   same /wp-json/fbl/v1/contact endpoint fbl-contact-system.php
   already exposes for /contact-us/, tagged with the "Request for
   Brochure" category that mu-plugin already pre-registers) - or,
   if no PDF has been uploaded on the Rates admin screen yet, a
   "coming soon" fallback linking to /contact-us/ instead. This is
   a shortcode rather than a static Divi code block (unlike
   /contact-us/'s) specifically so that fallback can be decided
   server-side, at render time, from fbl_get_brochure_pdf_id() - a
   static block has no way to branch on that.
   ========================================================= */
add_shortcode('fbl_brochure_form', function() {
    if (!fbl_get_brochure_pdf_id()) {
        ob_start();
        ?>
        <div class="fbl-brochure-fallback">
            <p>Our printable brochure is coming soon. In the meantime, please <a href="<?php echo esc_url(home_url('/contact-us/')); ?>">contact us directly</a> and we'll be happy to help.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <div class="fbl-brochure-form-wrap">
      <form id="fbl-brochure-form" onsubmit="return false;">
        <p>
          <label for="fbl-brochure-name">Name</label><br>
          <input type="text" id="fbl-brochure-name" name="name" required>
        </p>
        <p>
          <label for="fbl-brochure-email">Email</label><br>
          <input type="email" id="fbl-brochure-email" name="email" required>
        </p>

        <!-- Honeypot - same field name fbl-contact-system.php's
             calculate_spam_score() already checks for on every
             submission to this endpoint, humans never see this. -->
        <p style="position:absolute; left:-9999px;" aria-hidden="true">
          <label for="fbl-brochure-website">Website</label>
          <input type="text" id="fbl-brochure-website" name="website" tabindex="-1" autocomplete="off">
        </p>

        <button type="submit" id="fbl-brochure-submit">Send Me the Brochure</button>
        <p id="fbl-brochure-status" role="status"></p>
      </form>
    </div>
    <script>
    (function () {
        var form = document.getElementById('fbl-brochure-form');
        if (!form) return;

        var formTime = Math.floor(Date.now() / 1000);

        form.addEventListener('submit', function () {
            var status = document.getElementById('fbl-brochure-status');
            var btn    = document.getElementById('fbl-brochure-submit');
            btn.disabled = true;
            status.textContent = 'Sending...';

            fetch('<?php echo esc_url_raw(rest_url('fbl/v1/contact')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: document.getElementById('fbl-brochure-name').value,
                    email: document.getElementById('fbl-brochure-email').value,
                    // Fixed message, not a visible field - name + email is
                    // the whole form; the shared endpoint still requires a
                    // non-empty message, so this satisfies that without
                    // adding a textarea nobody needs to fill in.
                    message: 'Brochure requested via the website.',
                    category: 'Request for Brochure',
                    website: document.getElementById('fbl-brochure-website').value,
                    form_time: formTime
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    status.textContent = 'Thanks! Check your email for the brochure.';
                    form.reset();
                } else {
                    status.textContent = data.message || 'Something went wrong. Please try again or contact us directly.';
                    btn.disabled = false;
                }
            })
            .catch(function () {
                status.textContent = 'Something went wrong. Please try again or contact us directly.';
                btn.disabled = false;
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
});
