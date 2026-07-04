<?php
/* =========================================================
   FBL GALLERY - SHORTCODE BUILDER (admin page)
   Generates a ready-to-paste [fbl_gallery] shortcode with a
   live dropdown of FileBird folders.
   ========================================================= */

/* ---------------------------------------------------------
   Capabilities (mirrors catch-of-day pattern)
   --------------------------------------------------------- */
add_action('admin_init', function() {
    $editor = get_role('editor');
    $admin  = get_role('administrator');

    if ($editor) $editor->add_cap('manage_fbl_gallery');
    if ($admin)  $admin->add_cap('manage_fbl_gallery');
});

/* ---------------------------------------------------------
   Admin menu
   --------------------------------------------------------- */
add_action('admin_menu', function() {
    add_menu_page(
        'FBL Gallery Shortcode Builder',
        'Gallery Builder',
        'manage_fbl_gallery',
        'fbl-gallery-builder',
        'fbl_gallery_builder_page',
        'dashicons-format-gallery',
        32
    );
});

/* ---------------------------------------------------------
   Builder page
   --------------------------------------------------------- */
function fbl_gallery_builder_page() {
    global $wpdb;

    // FileBird folders with image counts
    $folders = $wpdb->get_results(
        "SELECT f.id, f.name, COUNT(fa.attachment_id) AS img_count
         FROM {$wpdb->prefix}fbv f
         LEFT JOIN {$wpdb->prefix}fbv_attachment_folder fa ON fa.folder_id = f.id
         LEFT JOIN {$wpdb->posts} p ON p.ID = fa.attachment_id
              AND p.post_type = 'attachment'
              AND p.post_mime_type LIKE 'image/%'
         GROUP BY f.id, f.name
         ORDER BY f.name ASC"
    );

    // Registered image sizes
    $sizes = get_intermediate_image_sizes();
    $sizes[] = 'full';
    ?>
    <div class="wrap">
        <h1>FBL Gallery Shortcode Builder</h1>
        <p>Pick a FileBird folder and options, then copy the generated shortcode into a Divi Code module.</p>

        <?php if (empty($folders)): ?>
            <div class="notice notice-warning"><p>No FileBird folders found. Create folders in Media Library first.</p></div>
        <?php endif; ?>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 640px;">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="fblgb-folder">Folder</label></th>
                    <td>
                        <select id="fblgb-folder" style="min-width: 260px;">
                            <?php foreach ($folders as $f): ?>
                                <option value="<?php echo esc_attr($f->name); ?>">
                                    <?php echo esc_html($f->name); ?> (<?php echo intval($f->img_count); ?> images)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-view">View</label></th>
                    <td>
                        <select id="fblgb-view">
                            <option value="grid">grid (default)</option>
                            <option value="carousel">carousel</option>
                            <option value="masonry">masonry</option>
                        </select>
                    </td>
                </tr>
                <tr class="fblgb-row-columns">
                    <th scope="row"><label for="fblgb-columns">Columns</label></th>
                    <td>
                        <select id="fblgb-columns">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3" selected>3 (default)</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                        </select>
                        <p class="description">Grid and masonry only. Drops to 2 columns under 980px, 1 under 600px.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-limit">Limit</label></th>
                    <td>
                        <input type="number" id="fblgb-limit" value="0" min="0" step="1" style="width: 90px;">
                        <p class="description">0 = show all images in the folder.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-size">Image size</label></th>
                    <td>
                        <select id="fblgb-size">
                            <?php foreach ($sizes as $s): ?>
                                <option value="<?php echo esc_attr($s); ?>" <?php selected($s, 'large'); ?>>
                                    <?php echo esc_html($s); ?><?php echo ($s === 'large') ? ' (default)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Thumbnail size shown on the page. Lightbox always opens the full image.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-order">Order</label></th>
                    <td>
                        <select id="fblgb-order">
                            <option value="date_desc">date_desc — newest first (default)</option>
                            <option value="date_asc">date_asc — oldest first</option>
                            <option value="name">name — alphabetical</option>
                            <option value="filebird">filebird — FileBird order</option>
                            <option value="random">random</option>
                        </select>
                    </td>
                </tr>
                <tr class="fblgb-row-shuffle" style="display: none;">
                    <th scope="row"><label for="fblgb-shuffle">Shuffle</label></th>
                    <td>
                        <select id="fblgb-shuffle">
                            <option value="pageload">pageload — new order every visit (default)</option>
                            <option value="daily">daily — changes at midnight</option>
                            <option value="weekly">weekly — changes Monday</option>
                            <option value="never">never — one fixed random order</option>
                        </select>
                    </td>
                </tr>
                <tr class="fblgb-row-autoplay" style="display: none;">
                    <th scope="row"><label for="fblgb-autoplay">Autoplay (ms)</label></th>
                    <td>
                        <input type="number" id="fblgb-autoplay" value="5000" min="1000" step="500" style="width: 110px;">
                        <p class="description">Carousel only. Time each slide is shown; 5000 = 5 seconds.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-caption">Lightbox caption</label></th>
                    <td>
                        <select id="fblgb-caption">
                            <option value="caption">caption — only if Caption field is filled (default)</option>
                            <option value="title">title — fall back to Title/filename</option>
                            <option value="none">none — never show captions</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-link">Click behavior</label></th>
                    <td>
                        <select id="fblgb-link">
                            <option value="lightbox">lightbox (default)</option>
                            <option value="none">none — images not clickable</option>
                        </select>
                    </td>
                </tr>
            </table>

            <hr>

            <h2 style="margin-top: 0;">Generated shortcode</h2>
            <p>
                <code id="fblgb-output" style="display: block; padding: 12px; background: #f0f0f1; font-size: 14px; user-select: all; word-break: break-all;"></code>
            </p>
            <p>
                <button type="button" class="button button-primary" id="fblgb-copy">Copy to Clipboard</button>
                <span id="fblgb-copied" style="margin-left: 10px; color: #00a32a; font-weight: bold; display: none;">Copied!</span>
            </p>
        </div>

        <script>
        (function() {
            var els = {
                folder:   document.getElementById('fblgb-folder'),
                view:     document.getElementById('fblgb-view'),
                columns:  document.getElementById('fblgb-columns'),
                limit:    document.getElementById('fblgb-limit'),
                size:     document.getElementById('fblgb-size'),
                order:    document.getElementById('fblgb-order'),
                shuffle:  document.getElementById('fblgb-shuffle'),
                autoplay: document.getElementById('fblgb-autoplay'),
                link:     document.getElementById('fblgb-link'),
                caption:  document.getElementById('fblgb-caption'),
                output:   document.getElementById('fblgb-output'),
                copyBtn:  document.getElementById('fblgb-copy'),
                copied:   document.getElementById('fblgb-copied')
            };

            var rowColumns  = document.querySelector('.fblgb-row-columns');
            var rowShuffle  = document.querySelector('.fblgb-row-shuffle');
            var rowAutoplay = document.querySelector('.fblgb-row-autoplay');

            function build() {
                var view  = els.view.value;
                var order = els.order.value;

                // Show/hide dependent rows
                rowColumns.style.display  = (view === 'carousel') ? 'none' : '';
                rowAutoplay.style.display = (view === 'carousel') ? '' : 'none';
                rowShuffle.style.display  = (order === 'random') ? '' : 'none';

                // Build shortcode, omitting values that equal defaults
                var parts = ['[fbl_gallery folder="' + els.folder.value + '"'];

                if (view !== 'grid') parts.push('view="' + view + '"');

                if (view !== 'carousel' && els.columns.value !== '3') {
                    parts.push('columns="' + els.columns.value + '"');
                }

                var limit = parseInt(els.limit.value, 10) || 0;
                if (limit > 0) parts.push('limit="' + limit + '"');

                if (els.size.value !== 'large') parts.push('size="' + els.size.value + '"');

                if (order !== 'date_desc') parts.push('order="' + order + '"');

                if (order === 'random' && els.shuffle.value !== 'pageload') {
                    parts.push('shuffle="' + els.shuffle.value + '"');
                }

                if (view === 'carousel') {
                    var ap = parseInt(els.autoplay.value, 10) || 5000;
                    if (ap !== 5000) parts.push('autoplay="' + ap + '"');
                }

                if (els.link.value !== 'lightbox') parts.push('link="' + els.link.value + '"');

                if (els.link.value === 'lightbox' && els.caption.value !== 'caption') {
                    parts.push('caption="' + els.caption.value + '"');
                }

                els.output.textContent = parts.join(' ') + ']';
            }

            ['folder', 'view', 'columns', 'limit', 'size', 'order', 'shuffle', 'autoplay', 'link', 'caption'].forEach(function(key) {
                els[key].addEventListener('change', build);
                els[key].addEventListener('input', build);
            });

            els.copyBtn.addEventListener('click', function() {
                var text = els.output.textContent;
                function done() {
                    els.copied.style.display = 'inline';
                    setTimeout(function() { els.copied.style.display = 'none'; }, 2000);
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                } else {
                    // Fallback for non-HTTPS contexts
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    done();
                }
            });

            build();
        })();
        </script>
    </div>
    <?php
}
