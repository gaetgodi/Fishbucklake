<?php
/* =========================================================
   FBL GALLERY - SHORTCODE BUILDER v2.0 (admin page)
   - Generates [fbl_gallery] shortcodes (FileBird folder dropdown)
   - Live preview panel (AJAX, renders real shortcode output)
   - Finds pages using the selected folder and can update
     their shortcodes in place (Divi 5 encoding-aware)
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
    add_media_page(
        'FBL Gallery Shortcode Builder',
        'Gallery Builder',
        'manage_fbl_gallery',
        'fbl-gallery-builder',
        'fbl_gallery_builder_page'
    );
});

/* ---------------------------------------------------------
   Enqueue frontend gallery CSS on the builder page so the
   preview looks close to the real thing
   --------------------------------------------------------- */
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'media_page_fbl-gallery-builder') return;

    wp_enqueue_style(
        'fbl-gallery',
        get_stylesheet_directory_uri() . '/css/gallery.css',
        array(),
        filemtime(get_stylesheet_directory() . '/css/gallery.css')
    );
});

/* ---------------------------------------------------------
   AJAX: render a shortcode preview
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_gallery_preview', function() {
    check_ajax_referer('fbl_gallery_builder', 'nonce');

    if (!current_user_can('manage_fbl_gallery')) {
        wp_send_json_error('Not allowed.');
    }

    $shortcode = isset($_POST['shortcode']) ? wp_unslash($_POST['shortcode']) : '';

    // Only allow our own shortcode through
    if (!preg_match('/^\[fbl_gallery\s[^\]]*\]$/', $shortcode)) {
        wp_send_json_error('Invalid shortcode.');
    }

    wp_send_json_success(do_shortcode($shortcode));
});

/* ---------------------------------------------------------
   Shortcode extraction helpers (Divi 5 encoding-aware)

   Divi 5 stores Code module content inside block-comment JSON
   where double quotes appear as an escape token, observed as
   \\u0022 in raw post_content. We never assume the token:
   we detect it per match and mirror it when replacing.
   --------------------------------------------------------- */

/**
 * Find all fbl_gallery shortcode occurrences in raw post content.
 * Returns array of ['raw' => stored string, 'decoded' => human form,
 *                   'token' => quote token used, 'folder' => folder attr]
 */
function fbl_gb_find_shortcodes_in_content($content) {
    $results = array();

    if (!preg_match_all('/\[fbl_gallery\b[^\]]*\]/', $content, $m)) {
        return $results;
    }

    foreach ($m[0] as $raw) {
        // Detect the quote token in order of specificity
        $token = '"';
        if (strpos($raw, '\\\\u0022') !== false) {
            $token = '\\\\u0022';
        } elseif (strpos($raw, '\\u0022') !== false) {
            $token = '\\u0022';
        } elseif (strpos($raw, '\\"') !== false) {
            $token = '\\"';
        }

        $decoded = ($token === '"') ? $raw : str_replace($token, '"', $raw);

        // Extract folder attribute from the decoded form
        $folder = '';
        if (preg_match('/folder="([^"]*)"/', $decoded, $fm)) {
            $folder = $fm[1];
        }

        $results[] = array(
            'raw'     => $raw,
            'decoded' => $decoded,
            'token'   => $token,
            'folder'  => $folder,
        );
    }

    return $results;
}

/**
 * Find all pages/posts containing an fbl_gallery shortcode for a folder.
 * Returns array of instances across posts.
 */
function fbl_gb_find_pages_using_folder($folder_name) {
    global $wpdb;

    $posts = $wpdb->get_results(
        "SELECT ID, post_title, post_status, post_type, post_content
         FROM {$wpdb->posts}
         WHERE post_content LIKE '%fbl_gallery%'
           AND post_type IN ('page', 'post')
           AND post_status IN ('publish', 'draft', 'private', 'pending')"
    );

    $instances = array();

    foreach ($posts as $p) {
        $found = fbl_gb_find_shortcodes_in_content($p->post_content);

        foreach ($found as $idx => $sc) {
            if ($sc['folder'] !== $folder_name) continue;

            $instances[] = array(
                'post_id'    => (int) $p->ID,
                'post_title' => $p->post_title,
                'status'     => $p->post_status,
                'instance'   => $idx,
                'raw'        => $sc['raw'],
                'decoded'    => $sc['decoded'],
                'token'      => $sc['token'],
                'view_url'   => get_permalink($p->ID),
                'edit_url'   => get_edit_post_link($p->ID, 'raw'),
            );
        }
    }

    return $instances;
}

/* ---------------------------------------------------------
   AJAX: find pages using the selected folder
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_gallery_find_pages', function() {
    check_ajax_referer('fbl_gallery_builder', 'nonce');

    if (!current_user_can('manage_fbl_gallery')) {
        wp_send_json_error('Not allowed.');
    }

    $folder = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
    if ($folder === '') {
        wp_send_json_error('No folder given.');
    }

    $instances = fbl_gb_find_pages_using_folder($folder);

    // Send only what the UI needs; raw travels base64-encoded as an
    // opaque handle so the update can do exact-match replacement
    $out = array();
    foreach ($instances as $i) {
        $out[] = array(
            'post_id'    => $i['post_id'],
            'post_title' => $i['post_title'],
            'status'     => $i['status'],
            'instance'   => $i['instance'],
            'decoded'    => $i['decoded'],
            'raw'        => base64_encode($i['raw']),
            'token'      => base64_encode($i['token']),
            'view_url'   => $i['view_url'],
        );
    }

    wp_send_json_success($out);
});
/* ---------------------------------------------------------
   AJAX: get image titles for a folder (for the Links panel)
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_gallery_folder_titles', function() {
    check_ajax_referer('fbl_gallery_builder', 'nonce');

    if (!current_user_can('manage_fbl_gallery')) {
        wp_send_json_error('Not allowed.');
    }

    $folder = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
    if ($folder === '') {
        wp_send_json_error('No folder given.');
    }

    $ids = fbl_gallery_get_ids($folder);
    if ($ids === null || empty($ids)) {
        wp_send_json_success(array());
    }

    $titles = array();
    foreach ($ids as $id) {
        $titles[] = get_the_title($id);
    }
    $titles = array_values(array_unique($titles));

    wp_send_json_success($titles);
});
/* ---------------------------------------------------------
   AJAX: update selected shortcode instances
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_gallery_update_pages', function() {
    check_ajax_referer('fbl_gallery_builder', 'nonce');

    if (!current_user_can('manage_fbl_gallery')) {
        wp_send_json_error('Not allowed.');
    }

    $new_shortcode = isset($_POST['new_shortcode']) ? wp_unslash($_POST['new_shortcode']) : '';
    if (!preg_match('/^\[fbl_gallery\s[^\]]*\]$/', $new_shortcode)) {
        wp_send_json_error('Invalid new shortcode.');
    }

    $items = isset($_POST['items']) ? json_decode(wp_unslash($_POST['items']), true) : null;
    if (!is_array($items) || empty($items)) {
        wp_send_json_error('Nothing selected.');
    }

    $report = array();

    foreach ($items as $item) {
        $post_id = isset($item['post_id']) ? (int) $item['post_id'] : 0;
        $raw     = isset($item['raw'])   ? base64_decode($item['raw'], true)   : false;
        $token   = isset($item['token']) ? base64_decode($item['token'], true) : false;

        if (!$post_id || $raw === false || $token === false) {
            $report[] = array('post_id' => $post_id, 'ok' => false, 'msg' => 'Bad request data.');
            continue;
        }

        $post = get_post($post_id);
        if (!$post || !current_user_can('edit_post', $post_id)) {
            $report[] = array('post_id' => $post_id, 'ok' => false, 'msg' => 'Post not found or not editable.');
            continue;
        }

        // Exact-match safety: the stored shortcode must still be present verbatim
        if (strpos($post->post_content, $raw) === false) {
            $report[] = array(
                'post_id' => $post_id,
                'ok'      => false,
                'msg'     => 'Page changed since Find - not updated. Re-run Find.',
            );
            continue;
        }

        // Re-encode the new shortcode with the exact token the old one used
        $encoded_new = ($token === '"') ? $new_shortcode : str_replace('"', $token, $new_shortcode);

        // Replace only the first occurrence of this exact raw string
        $pos = strpos($post->post_content, $raw);
        $new_content = substr_replace($post->post_content, $encoded_new, $pos, strlen($raw));

        $result = wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => wp_slash($new_content),
        ), true);

        if (is_wp_error($result)) {
            $report[] = array('post_id' => $post_id, 'ok' => false, 'msg' => $result->get_error_message());
            continue;
        }

        // Clear caches so the change is visible immediately
        clean_post_cache($post_id);
        if (function_exists('et_core_clear_cache')) {
            et_core_clear_cache();
        }
        if (function_exists('wp_cache_post_change')) { // WP Super Cache
            wp_cache_post_change($post_id);
        }

        $report[] = array(
            'post_id'  => $post_id,
            'ok'       => true,
            'msg'      => 'Updated (revision saved as undo).',
            'view_url' => get_permalink($post_id),
        );
    }

    wp_send_json_success($report);
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
         ORDER BY
            CASE
                WHEN f.name LIKE 'WEB\_%' THEN 0
                WHEN f.name LIKE '%\_FBL'  THEN 1
                ELSE 2
            END,
            f.name ASC"
    );

    // Registered image sizes
    $sizes = get_intermediate_image_sizes();
    $sizes[] = 'full';

    $nonce = wp_create_nonce('fbl_gallery_builder');
    ?>
    <div class="wrap">
        <h1>FBL Gallery Shortcode Builder</h1>
        <p>Pick a FileBird folder and options. Preview live, copy the shortcode, or update pages already using this folder.</p>

        <?php if (empty($folders)): ?>
            <div class="notice notice-warning"><p>No FileBird folders found. Create folders in Media Library first.</p></div>
        <?php endif; ?>

        <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">

        <!-- ================= OPTIONS PANEL ================= -->
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 640px; flex: 0 0 auto;">
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
                            <option value="date_desc">date_desc - newest first (default)</option>
                            <option value="date_asc">date_asc - oldest first</option>
                            <option value="name">name - by Title label, A-Z</option>
                            <option value="name_desc">name_desc - by Title label, Z-A</option>
                            <option value="random">random</option>
                        </select>
                    </td>
                </tr>
                <tr class="fblgb-row-shuffle" style="display: none;">
                    <th scope="row"><label for="fblgb-shuffle">Shuffle</label></th>
                    <td>
                        <select id="fblgb-shuffle">
                            <option value="pageload">pageload - new order every visit (default)</option>
                            <option value="daily">daily - changes at midnight</option>
                            <option value="weekly">weekly - changes Monday</option>
                            <option value="never">never - one fixed random order</option>
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
                            <option value="caption">caption - only if Caption field is filled (default)</option>
                            <option value="title">title - fall back to Title/filename</option>
                            <option value="none">none - never show captions</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-tcaption">Thumbnail caption</label></th>
                    <td>
                        <select id="fblgb-tcaption">
                            <option value="none">none - no text under thumbnails (default)</option>
                            <option value="caption">caption - only if Caption field is filled</option>
                            <option value="title">title - fall back to Title/filename</option>
                        </select>
                    </td>
                </tr>
                <tr class="fblgb-row-fit">
                    <th scope="row"><label for="fblgb-fit">Image fit</label></th>
                    <td>
                        <select id="fblgb-fit">
                            <option value="cover">cover - fill the frame, crop overflow (default)</option>
                            <option value="contain">contain - full image, letterbox if needed</option>
                        </select>
                        <p class="description">Carousel and grid. Masonry always shows full images.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fblgb-link">Click behavior</label></th>
                    <td>
                        <select id="fblgb-link">
                            <option value="lightbox">lightbox (default)</option>
                            <option value="none">none - images not clickable</option>
                        </select>
                    </td>
                </tr>
            </table>

            <hr>

            <h2>Page links (optional)</h2>
            <p class="description">
                Turn specific images into links to other pages instead of the lightbox.
                Leave a field blank to keep that image's normal behavior.
            </p>
            <div id="fblgb-links-wrap">
                <em>Select a folder to see its images here.</em>
            </div>

            <hr>

            <h2 style="margin-top: 0;">Generated shortcode</h2>
            <p>
                <code id="fblgb-output" style="display: block; padding: 12px; background: #f0f0f1; font-size: 14px; user-select: all; word-break: break-all;"></code>
            </p>
            <p>
                <button type="button" class="button button-primary" id="fblgb-copy">Copy to Clipboard</button>
                <span id="fblgb-copied" style="margin-left: 10px; color: #00a32a; font-weight: bold; display: none;">Copied!</span>
            </p>

            <hr>

            <h2>Pages using this folder</h2>
            <p>
                <button type="button" class="button" id="fblgb-find">Find Pages</button>
                <span id="fblgb-find-status" style="margin-left: 10px;"></span>
            </p>
            <div id="fblgb-pages"></div>
            <p id="fblgb-update-wrap" style="display: none;">
                <button type="button" class="button button-primary" id="fblgb-update">Update Selected</button>
                <span class="description" style="margin-left: 10px;">A revision is saved for each page - undo via page revision history.</span>
            </p>
            <div id="fblgb-update-report"></div>
        </div>

        <!-- ================= PREVIEW PANEL ================= -->
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; flex: 1 1 500px; min-width: 400px;">
            <h2 style="margin-top: 0;">Preview
                <span class="description" style="font-weight: normal; font-size: 12px;">
                    (approximate - lightbox and carousel autoplay only run on the real page)
                </span>
            </h2>
            <div id="fblgb-preview" style="border: 1px dashed #ccc; padding: 12px; min-height: 120px;">
                <em>Adjust options to load preview...</em>
            </div>
        </div>

        </div><!-- /flex -->

        <script>
        (function() {
            var nonce = '<?php echo esc_js($nonce); ?>';
            var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

            var els = {
                folder:   document.getElementById('fblgb-folder'),
                view:     document.getElementById('fblgb-view'),
                columns:  document.getElementById('fblgb-columns'),
                limit:    document.getElementById('fblgb-limit'),
                size:     document.getElementById('fblgb-size'),
                order:    document.getElementById('fblgb-order'),
                shuffle:  document.getElementById('fblgb-shuffle'),
                autoplay: document.getElementById('fblgb-autoplay'),
                caption:  document.getElementById('fblgb-caption'),
                tcaption: document.getElementById('fblgb-tcaption'),
                fit:      document.getElementById('fblgb-fit'),
                link:     document.getElementById('fblgb-link'),
                linksWrap: document.getElementById('fblgb-links-wrap'),
                output:   document.getElementById('fblgb-output'),
                copyBtn:  document.getElementById('fblgb-copy'),
                copied:   document.getElementById('fblgb-copied'),
                preview:  document.getElementById('fblgb-preview'),
                findBtn:  document.getElementById('fblgb-find'),
                findStatus: document.getElementById('fblgb-find-status'),
                pages:    document.getElementById('fblgb-pages'),
                updateWrap: document.getElementById('fblgb-update-wrap'),
                updateBtn:  document.getElementById('fblgb-update'),
                report:   document.getElementById('fblgb-update-report')
            };

            var rowColumns  = document.querySelector('.fblgb-row-columns');
            var rowShuffle  = document.querySelector('.fblgb-row-shuffle');
            var rowAutoplay = document.querySelector('.fblgb-row-autoplay');

            var foundInstances = [];
            var previewTimer = null;
            var folderTitles = [];
            var linksLoadedFor = ''; var foundInstances = [];
            var previewTimer = null;

            function build() {
                var view  = els.view.value;
                var order = els.order.value;

                rowColumns.style.display  = (view === 'carousel') ? 'none' : '';
                rowAutoplay.style.display = (view === 'carousel') ? '' : 'none';
                rowShuffle.style.display  = (order === 'random') ? '' : 'none';

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

                var linksAttr = buildLinksAttr();
                if (linksAttr) parts.push('links="' + linksAttr + '"');

                if (els.link.value === 'lightbox' && els.caption.value !== 'caption') {
                    parts.push('caption="' + els.caption.value + '"');
                }

                if (els.tcaption.value !== 'none') {
                    parts.push('thumb_caption="' + els.tcaption.value + '"');
                }

                if (els.fit.value !== 'cover' && els.view.value !== 'masonry') {
                    parts.push('fit="' + els.fit.value + '"');
                }

                els.output.textContent = parts.join(' ') + ']';

                schedulePreview();
            }
            function loadFolderTitles() {
                var folder = els.folder.value;
                if (folder === linksLoadedFor) return;
                linksLoadedFor = folder;

                // Clear stale links immediately so the shortcode never carries
                // a previous folder's titles while the new list loads.
                folderTitles = [];
                els.linksWrap.innerHTML = '<em>Loading images...</em>';
                build();

                var body = new URLSearchParams();
                body.append('action', 'fbl_gallery_folder_titles');
                body.append('nonce', nonce);
                body.append('folder', folder);

                fetch(ajaxurl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success || !res.data.length) {
                            folderTitles = [];
                            els.linksWrap.innerHTML = '<em>No images found in this folder.</em>';
                            return;
                        }
                        folderTitles = res.data;
                        renderLinksPanel();
                    })
                    .catch(function() {
                        els.linksWrap.innerHTML = '<em>Could not load images.</em>';
                    });
            }

            function renderLinksPanel() {
                var html = '<table class="widefat striped"><tbody>';
                folderTitles.forEach(function(title, i) {
                    html += '<tr>' +
                        '<td style="width:40%;">' + escapeHtml(title) + '</td>' +
                        '<td><input type="text" class="fblgb-link-input" data-title="' +
                        escapeHtml(title).replace(/"/g, '&quot;') +
                        '" placeholder="/page-slug/ or leave blank" style="width:100%;"></td>' +
                        '</tr>';
                });
                html += '</tbody></table>';
                els.linksWrap.innerHTML = html;

                els.linksWrap.querySelectorAll('.fblgb-link-input').forEach(function(inp) {
                    inp.addEventListener('input', build);
                });
            }

            function buildLinksAttr() {
                if (!els.linksWrap) return '';
                var pairs = [];
                els.linksWrap.querySelectorAll('.fblgb-link-input').forEach(function(inp) {
                    var url = inp.value.trim();
                    if (!url) return;
                    var title = inp.getAttribute('data-title') || '';
                    // titles/urls containing : or , would break the simple format - skip and warn
                    if (title.indexOf(':') !== -1 || title.indexOf(',') !== -1 ||
                        url.indexOf(':') !== -1 && url.indexOf('http') !== 0 && url.indexOf('/') !== 0) {
                        // allow http(s):// URLs (they contain ':') but flag other stray colons
                    }
                    pairs.push(title + ':' + url);
                });
                return pairs.join(',');
            }
            function schedulePreview() {
                if (previewTimer) clearTimeout(previewTimer);
                previewTimer = setTimeout(loadPreview, 500);
            }

            function loadPreview() {
                var body = new URLSearchParams();
                body.append('action', 'fbl_gallery_preview');
                body.append('nonce', nonce);
                body.append('shortcode', els.output.textContent);

                fetch(ajaxurl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            els.preview.innerHTML = res.data ||
                                '<em>Shortcode produced no output (empty folder?).</em>';
                            // Carousel preview: show first slide statically
                            els.preview.querySelectorAll('.fbl-gallery--carousel .fbl-gallery-item').forEach(function(s, i) {
                                s.classList.toggle('is-active', i === 0);
                            });
                        } else {
                            els.preview.innerHTML = '<em>Preview error: ' + (res.data || 'unknown') + '</em>';
                        }
                    })
                    .catch(function() {
                        els.preview.innerHTML = '<em>Preview request failed.</em>';
                    });
            }

            function findPages() {
                els.findStatus.textContent = 'Searching...';
                els.pages.innerHTML = '';
                els.report.innerHTML = '';
                els.updateWrap.style.display = 'none';
                foundInstances = [];

                var body = new URLSearchParams();
                body.append('action', 'fbl_gallery_find_pages');
                body.append('nonce', nonce);
                body.append('folder', els.folder.value);

                fetch(ajaxurl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) {
                            els.findStatus.textContent = 'Error: ' + (res.data || 'unknown');
                            return;
                        }

                        foundInstances = res.data;

                        if (foundInstances.length === 0) {
                            els.findStatus.textContent = 'No pages use this folder yet.';
                            return;
                        }

                        els.findStatus.textContent = foundInstances.length + ' instance(s) found.';

                        var html = '<table class="widefat striped"><thead><tr>' +
                            '<th style="width:30px;"></th><th>Page</th><th>Status</th>' +
                            '<th>Current shortcode</th><th style="width:70px;"></th>' +
                            '</tr></thead><tbody>';

                        foundInstances.forEach(function(inst, i) {
                            html += '<tr>' +
                                '<td><input type="checkbox" class="fblgb-inst" data-i="' + i + '"></td>' +
                                '<td>' + escapeHtml(inst.post_title) + '</td>' +
                                '<td>' + escapeHtml(inst.status) + '</td>' +
                                '<td><code style="font-size:12px;">' + escapeHtml(inst.decoded) + '</code></td>' +
                                '<td><a href="' + inst.view_url + '" target="_blank">View</a></td>' +
                                '</tr>';
                        });

                        html += '</tbody></table>' +
                            '<p class="description" style="margin-top:8px;">New shortcode that will replace checked rows:<br>' +
                            '<code style="font-size:12px;">' + escapeHtml(els.output.textContent) + '</code></p>';

                        els.pages.innerHTML = html;
                        els.updateWrap.style.display = '';
                    })
                    .catch(function() {
                        els.findStatus.textContent = 'Request failed.';
                    });
            }

            function updatePages() {
                var selected = [];
                els.pages.querySelectorAll('.fblgb-inst:checked').forEach(function(cb) {
                    var inst = foundInstances[parseInt(cb.getAttribute('data-i'), 10)];
                    selected.push({ post_id: inst.post_id, raw: inst.raw, token: inst.token });
                });

                if (selected.length === 0) {
                    alert('Select at least one page to update.');
                    return;
                }

                if (!confirm('Replace the shortcode on ' + selected.length + ' page(s) with:\n\n' +
                             els.output.textContent + '\n\nA revision is saved for each page.')) {
                    return;
                }

                els.report.innerHTML = '<em>Updating...</em>';

                var body = new URLSearchParams();
                body.append('action', 'fbl_gallery_update_pages');
                body.append('nonce', nonce);
                body.append('new_shortcode', els.output.textContent);
                body.append('items', JSON.stringify(selected));

                fetch(ajaxurl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) {
                            els.report.innerHTML = '<p style="color:#dc3232;">Error: ' +
                                escapeHtml(String(res.data || 'unknown')) + '</p>';
                            return;
                        }

                        var html = '<ul style="margin-top:10px;">';
                        res.data.forEach(function(r) {
                            if (r.ok) {
                                html += '<li style="color:#00a32a;">Post ' + r.post_id + ': ' +
                                    escapeHtml(r.msg) +
                                    ' <a href="' + r.view_url + '" target="_blank">View page</a></li>';
                            } else {
                                html += '<li style="color:#dc3232;">Post ' + r.post_id + ': ' +
                                    escapeHtml(r.msg) + '</li>';
                            }
                        });
                        html += '</ul>';
                        els.report.innerHTML = html;

                        // Refresh the list so raw handles stay valid
                        findPages();
                    })
                    .catch(function() {
                        els.report.innerHTML = '<p style="color:#dc3232;">Update request failed.</p>';
                    });
            }

            function escapeHtml(s) {
                var d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            ['view', 'columns', 'limit', 'size', 'order', 'shuffle',
            'autoplay', 'caption', 'tcaption', 'fit', 'link'].forEach(function(key) {
                els[key].addEventListener('change', build);
                els[key].addEventListener('input', build);
            });

            // Folder changes: clear+rebuild happens inside loadFolderTitles,
            // so it alone drives both the panel and the shortcode.
            els.folder.addEventListener('change', loadFolderTitles);

            els.copyBtn.addEventListener('click', function() {
                var text = els.output.textContent;
                function done() {
                    els.copied.style.display = 'inline';
                    setTimeout(function() { els.copied.style.display = 'none'; }, 2000);
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    done();
                }
            });

            els.findBtn.addEventListener('click', findPages);
            els.updateBtn.addEventListener('click', updatePages);

            build();
            loadFolderTitles();
        })();
        </script>
    </div>
    <?php
}
