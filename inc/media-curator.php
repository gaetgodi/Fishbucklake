<?php
/* =========================================================
   FBL MEDIA CURATOR
   Folder-scoped Title/Caption editor + copy-to-WEB tool.

   - Pick a source FileBird folder; edit Title & Caption inline.
   - Table live-sorts by Title (drives [fbl_gallery order="name"]).
   - Flag rows to copy Title -> Caption in bulk (only flagged rows).
   - Select rows and copy (folder-membership, non-destructive) into a
     WEB_ target folder. Per-row copy and batch copy both supported.
   - Target folder is created at root if it does not yet exist.

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

// All FileBird folders, _FBL sorted to top, then alpha.
function fbl_curator_all_folders() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}fbv ORDER BY name ASC"
    );
    if (!$rows) return array();
    usort($rows, function ($a, $b) {
        $af = (substr($a->name, -4) === '_FBL') ? 0 : 1;
        $bf = (substr($b->name, -4) === '_FBL') ? 0 : 1;
        if ($af !== $bf) return $af - $bf;
        return strcasecmp($a->name, $b->name);
    });
    return $rows;
}

// Folder id by exact name.
function fbl_curator_folder_id($name) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}fbv WHERE name = %s", $name
    ));
}

// Create a root folder if absent; return its id.
function fbl_curator_get_or_create_folder($name) {
    $id = fbl_curator_folder_id($name);
    if ($id) return $id;
    global $wpdb;
    $wpdb->insert(
        "{$wpdb->prefix}fbv",
        array('name' => $name, 'parent' => 0, 'type' => 0, 'ord' => 0, 'created_by' => get_current_user_id()),
        array('%s', '%d', '%d', '%d', '%d')
    );
    return (int) $wpdb->insert_id;
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
            <code>[fbl_gallery order="name"]</code>. Flag rows to copy their title into
            the caption. Select rows and copy them (non-destructively) into a WEB target folder.
        </p>

        <div class="fbl-curator-toolbar">
            <label><strong>Source folder:</strong>
                <select id="fbl-curator-source">
                    <option value="">— choose —</option>
                    <?php foreach ($folders as $f): ?>
                        <option value="<?php echo esc_attr($f->name); ?>"><?php echo esc_html($f->name); ?></option>
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
                    <th style="width:50px;" title="Flag for caption-copy">Flag</th>
                    <th style="width:60px;" title="Select for WEB copy">Select</th>
                    <th style="width:90px;">Copy</th>
                </tr>
            </thead>
            <tbody id="fbl-curator-rows"></tbody>
        </table>

        <p id="fbl-curator-empty" style="display:none;"><em>This folder has no images.</em></p>
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
        $items[] = array(
            'id'       => $id,
            'thumb'    => wp_get_attachment_image_url($id, 'thumbnail'),
            'filename' => basename(get_attached_file($id)),
            'title'    => get_the_title($id),
            'caption'  => wp_get_attachment_caption($id),
        );
    }
    // sort by title, case-insensitive
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
    } else { // caption is the excerpt on an attachment
        wp_update_post(array('ID' => $id, 'post_excerpt' => $value));
    }
    wp_send_json_success();
});

/* ---------------------------------------------------------
   AJAX: copy title -> caption for a set of ids (flagged rows)
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_curator_copycaptions', function () {
    check_ajax_referer('fbl_curator', 'nonce');
    if (!current_user_can('upload_files')) wp_send_json_error('forbidden');

    $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();
    if (empty($ids)) wp_send_json_error('no rows flagged');

    $done = 0;
    foreach ($ids as $id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'attachment') continue;
        wp_update_post(array('ID' => $id, 'post_excerpt' => $post->post_title));
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

    $tid = fbl_curator_get_or_create_folder($target);
    if (!$tid) wp_send_json_error('could not create/find target folder');

    global $wpdb;
    $table = "{$wpdb->prefix}fbv_attachment_folder";
    $copied = 0;
    foreach ($ids as $id) {
        // INSERT IGNORE semantics: skip if already present (composite PK)
        $res = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (folder_id, attachment_id) VALUES (%d, %d)",
            $tid, $id
        ));
        if ($res) $copied++;
    }
    fbl_curator_flush();

    wp_send_json_success(array(
        'target'  => $target,
        'copied'  => $copied,
        'skipped' => count($ids) - $copied,
    ));
});
