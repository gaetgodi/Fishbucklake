<?php
/* =========================================================
   FBL MEDIA CURATOR
   Folder-scoped Title/Caption editor + copy-to-WEB tool.

   - Pick a source FileBird folder; edit Title & Caption inline.
   - Table live-sorts by Title (drives [fbl_gallery order="name"]).
   - Empty captions are PRE-FILLED with the title as a muted suggestion
     (not saved). Flag rows + "Copy title -> caption" commits them.
   - Select rows and copy (folder-membership, non-destructive) into a
     WEB_ target folder. Per-row copy and batch copy both supported.
   - Target folder is created at root if it does not yet exist; new
     folders are pushed back to the source dropdown live.
   - Select-all toggles for both Flag and Select columns.
   - Hover a thumbnail for a larger preview.

   Admin page: Media > Media Curator
   ========================================================= */

if (!defined('ABSPATH')) exit;

/* ---------------------------------------------------------
   Admin menu
   --------------------------------------------------------- */
add_action('admin_menu', function () {
    add_media_page(
        'Media Curator',
        'Media Curator',
        'upload_files',
        'fbl-media-curator',
        'fbl_media_curator_render_page'
    );
});

/* ---------------------------------------------------------
   Helpers
   --------------------------------------------------------- */

// All FileBird folders with image counts, _FBL sorted to top, then alpha.
function fbl_curator_all_folders() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT f.id, f.name, COUNT(fa.attachment_id) AS cnt
         FROM {$wpdb->prefix}fbv f
         LEFT JOIN {$wpdb->prefix}fbv_attachment_folder fa ON fa.folder_id = f.id
         LEFT JOIN {$wpdb->posts} p
                ON p.ID = fa.attachment_id
               AND p.post_type = 'attachment'
               AND p.post_mime_type LIKE 'image/%%'
         GROUP BY f.id, f.name"
    );
    if (!$rows) return array();
    // The LEFT JOIN on posts makes non-image / missing rows NULL, and
    // COUNT() ignores NULLs, so cnt reflects image attachments only.
    usort($rows, function ($a, $b) {
        $af = (substr($a->name, -4) === '_FBL') ? 0 : 1;
        $bf = (substr($b->name, -4) === '_FBL') ? 0 : 1;
        if ($af !== $bf) return $af - $bf;
        return strcasecmp($a->name, $b->name);
    });
    return $rows;
}

// Count images in a single folder (used after creating a new one).
function fbl_curator_folder_count($folder_id) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(fa.attachment_id)
         FROM {$wpdb->prefix}fbv_attachment_folder fa
         INNER JOIN {$wpdb->posts} p ON p.ID = fa.attachment_id
         WHERE fa.folder_id = %d
           AND p.post_type = 'attachment'
           AND p.post_mime_type LIKE 'image/%%'",
        $folder_id
    ));
}

// Folder id by exact name.
function fbl_curator_folder_id($name) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}fbv WHERE name = %s", $name
    ));
}

// Create a root folder if absent; return array(id, created_bool).
function fbl_curator_get_or_create_folder($name) {
    $id = fbl_curator_folder_id($name);
    if ($id) return array($id, false);
    global $wpdb;
    $wpdb->insert(
        "{$wpdb->prefix}fbv",
        array('name' => $name, 'parent' => 0, 'type' => 0, 'ord' => 0, 'created_by' => get_current_user_id()),
        array('%s', '%d', '%d', '%d', '%d')
    );
    return array((int) $wpdb->insert_id, true);
}

// Flush the gallery cache if that function exists (it lives in gallery.php).
function fbl_curator_flush() {
    if (function_exists('fbl_gallery_flush_cache')) {
        fbl_gallery_flush_cache();
    }
}

/* ---------------------------------------------------------
   Admin page markup
   --------------------------------------------------------- */
function fbl_media_curator_render_page() {
    if (!current_user_can('upload_files')) return;
    $folders = fbl_curator_all_folders();
    $nonce   = wp_create_nonce('fbl_curator');
    ?>
    <div class="wrap fbl-curator">
        <h1>Media Curator</h1>
        <p class="description">
            Pick a source folder to edit titles and captions. Titles drive
            <code>[fbl_gallery order="name"]</code>. Empty captions are pre-filled with the
            title as a muted suggestion — flag rows and use the button to commit them.
            Select rows and copy them (non-destructively) into a WEB target folder.
        </p>

        <div class="fbl-curator-toolbar">
            <label><strong>Source folder:</strong>
                <select id="fbl-curator-source">
                    <option value="">— choose —</option>
                    <?php foreach ($folders as $f): ?>
                        <option value="<?php echo esc_attr($f->name); ?>">
                            <?php echo esc_html($f->name . ' (' . (int) $f->cnt . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span id="fbl-curator-count" class="fbl-curator-count"></span>
        </div>

        <div class="fbl-curator-actions" style="display:none;">
            <button type="button" class="button" id="fbl-curator-copycaptions">
                Copy title → caption for flagged rows
            </button>

            <span class="fbl-curator-sep">|</span>

            <label>Copy selected to:
                <input type="text" id="fbl-curator-target" value="WEB_" size="22" />
            </label>
            <button type="button" class="button button-primary" id="fbl-curator-batchcopy">
                Copy selected →
            </button>
            <span id="fbl-curator-status" class="fbl-curator-status"></span>
        </div>

        <table class="widefat fixed striped fbl-curator-table" style="display:none; margin-top:1rem;">
            <thead>
                <tr>
                    <th style="width:70px;">Image</th>
                    <th>Title</th>
                    <th>Caption</th>
                    <th style="width:50px;" title="Flag for caption-copy">
                        Flag<br><input type="checkbox" id="fbl-curator-flagall" title="Flag all">
                    </th>
                    <th style="width:60px;" title="Select for WEB copy">
                        Select<br><input type="checkbox" id="fbl-curator-selectall" title="Select all">
                    </th>
                    <th style="width:90px;">Copy</th>
                </tr>
            </thead>
            <tbody id="fbl-curator-rows"></tbody>
        </table>

        <p id="fbl-curator-empty" style="display:none;"><em>This folder has no images.</em></p>

        <div id="fbl-curator-hover" class="fbl-curator-hover" aria-hidden="true"></div>
    </div>

    <script>
    window.FBL_CURATOR = {
        ajax:  <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
        nonce: <?php echo json_encode($nonce); ?>
    };
    </script>
    <?php
}

/* ---------------------------------------------------------
   Enqueue admin assets (only on our page)
   --------------------------------------------------------- */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'media_page_fbl-media-curator') return;
    $theme = get_stylesheet_directory_uri();
    $ver   = wp_get_theme()->get('Version');
    wp_enqueue_style('fbl-curator', $theme . '/css/media-curator.css', array(), $ver);
    wp_enqueue_script('fbl-curator', $theme . '/js/media-curator.js', array(), $ver, true);
});

/* ---------------------------------------------------------
   AJAX: load a folder's images
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_curator_load', function () {
    check_ajax_referer('fbl_curator', 'nonce');
    if (!current_user_can('upload_files')) wp_send_json_error('forbidden');

    $folder = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
    $fid = fbl_curator_folder_id($folder);
    if (!$fid) wp_send_json_error('folder not found');

    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT fa.attachment_id
         FROM {$wpdb->prefix}fbv_attachment_folder fa
         INNER JOIN {$wpdb->posts} p ON p.ID = fa.attachment_id
         WHERE fa.folder_id = %d
           AND p.post_type = 'attachment'
           AND p.post_mime_type LIKE 'image/%%'",
        $fid
    ));

    $items = array();
    foreach ($ids as $id) {
        $id = (int) $id;
        $caption = wp_get_attachment_caption($id);
        $title   = get_the_title($id);
        $items[] = array(
            'id'         => $id,
            'thumb'      => wp_get_attachment_image_url($id, 'thumbnail'),
            'large'      => wp_get_attachment_image_url($id, 'large'),
            'filename'   => basename(get_attached_file($id)),
            'title'      => $title,
            'caption'    => $caption,
            // suggestion is shown (muted) only when caption is empty
            'suggestion' => ($caption === '' || $caption === null) ? $title : '',
        );
    }
    usort($items, function ($a, $b) { return strcasecmp($a['title'], $b['title']); });

    wp_send_json_success(array('items' => $items, 'count' => count($items)));
});

/* ---------------------------------------------------------
   AJAX: save a single field (title or caption)
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_curator_save', function () {
    check_ajax_referer('fbl_curator', 'nonce');
    if (!current_user_can('upload_files')) wp_send_json_error('forbidden');

    $id    = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
    $value = isset($_POST['value']) ? wp_kses_post(wp_unslash($_POST['value'])) : '';

    if (!$id || !in_array($field, array('title', 'caption'), true)) {
        wp_send_json_error('bad request');
    }

    $post = get_post($id);
    if (!$post || $post->post_type !== 'attachment') wp_send_json_error('not an attachment');

    if ($field === 'title') {
        wp_update_post(array('ID' => $id, 'post_title' => sanitize_text_field($value)));
    } else {
        wp_update_post(array('ID' => $id, 'post_excerpt' => $value));
    }
    wp_send_json_success();
});

/* ---------------------------------------------------------
   AJAX: commit captions for flagged rows.
   Writes the supplied value per id (so committed suggestions match
   what the user saw); falls back to the post title if none supplied.
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_curator_copycaptions', function () {
    check_ajax_referer('fbl_curator', 'nonce');
    if (!current_user_can('upload_files')) wp_send_json_error('forbidden');

    // values arrive as id => caption pairs (parallel arrays)
    $ids  = isset($_POST['ids'])  ? array_map('intval', (array) $_POST['ids']) : array();
    $vals = isset($_POST['vals']) ? (array) wp_unslash($_POST['vals']) : array();
    if (empty($ids)) wp_send_json_error('no rows flagged');

    $done = 0;
    foreach ($ids as $i => $id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'attachment') continue;
        $caption = isset($vals[$i]) ? wp_kses_post($vals[$i]) : $post->post_title;
        wp_update_post(array('ID' => $id, 'post_excerpt' => $caption));
        $done++;
    }
    wp_send_json_success(array('updated' => $done));
});

/* ---------------------------------------------------------
   AJAX: copy attachments into a WEB target folder (membership)
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_curator_copyweb', function () {
    check_ajax_referer('fbl_curator', 'nonce');
    if (!current_user_can('upload_files')) wp_send_json_error('forbidden');

    $ids    = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();
    $target = isset($_POST['target']) ? sanitize_text_field(wp_unslash($_POST['target'])) : '';
    $target = trim($target);

    if (empty($ids))       wp_send_json_error('no images selected');
    if ($target === '' || $target === 'WEB_') wp_send_json_error('enter a target folder name');

    list($tid, $created) = fbl_curator_get_or_create_folder($target);
    if (!$tid) wp_send_json_error('could not create/find target folder');

    global $wpdb;
    $table = "{$wpdb->prefix}fbv_attachment_folder";
    $copied = 0;
    foreach ($ids as $id) {
        $res = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (folder_id, attachment_id) VALUES (%d, %d)",
            $tid, $id
        ));
        if ($res) $copied++;
    }
    fbl_curator_flush();

    wp_send_json_success(array(
        'target'    => $target,
        'copied'    => $copied,
        'skipped'   => count($ids) - $copied,
        'created'   => $created,
        'new_count' => fbl_curator_folder_count($tid),
    ));
});