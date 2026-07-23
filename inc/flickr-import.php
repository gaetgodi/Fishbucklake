<?php
/* =========================================================
   FBL FLICKR IMPORT
   Upload a Flickr album .zip -> extract -> resize -> import into
   the Media Library -> assign to a new "<Album>_FBL" FileBird folder.

   - Chunked upload: the browser sends 5 MB pieces, so nginx's
     client_max_body_size is never hit regardless of album size.
   - Resize only (no crop). Images are pre-cropped in Flickr.
   - Resizing uses the Imagick extension when available, GD otherwise.
   - Importing is BATCHED over repeated AJAX calls so large albums
     do not hit an execution timeout.
   - Folder name comes from the zip's inner top directory if there is
     exactly one, else the zip filename; "_FBL" is appended. Collisions
     get a sequence BEFORE the suffix ("Album-2_FBL").
   - Zip-slip protection: entries with .. or absolute paths are rejected.
     Non-image entries are skipped.

   Admin page: Media > Flickr Import
   ========================================================= */

if (!defined('ABSPATH')) exit;

define('FBL_FI_BATCH', 5); // images processed per AJAX call

/* ---------------------------------------------------------
   Admin menu
   --------------------------------------------------------- */
add_action('admin_menu', function () {
    add_media_page(
        'Flickr Import',
        'Flickr Import',
        'upload_files',
        'fbl-flickr-import',
        'fbl_flickr_import_page'
    );
});

/* ---------------------------------------------------------
   Helpers
   --------------------------------------------------------- */

// Working directory for extraction.
function fbl_fi_workdir() {
    $up = wp_upload_dir();
    $dir = trailingslashit($up['basedir']) . '_fbl_import';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        @file_put_contents($dir . '/.htaccess', "Deny from all\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function fbl_fi_folder_id($name) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}fbv WHERE name = %s", $name
    ));
}

function fbl_fi_create_folder($name) {
    global $wpdb;
    $wpdb->insert(
        "{$wpdb->prefix}fbv",
        array('name' => $name, 'parent' => 0, 'type' => 0, 'ord' => 0, 'created_by' => get_current_user_id()),
        array('%s', '%d', '%d', '%d', '%d')
    );
    return (int) $wpdb->insert_id;
}

/**
 * Turn a base album name into a unique "<Album>_FBL" folder name.
 * Sequence goes BEFORE the suffix: "Album-2_FBL".
 */
function fbl_fi_unique_folder_name($base) {
    $base = trim(preg_replace('/\s+/', ' ', $base));
    if ($base === '') $base = 'Flickr Album';

    $candidate = $base . '_FBL';
    if (!fbl_fi_folder_id($candidate)) return $candidate;

    for ($i = 2; $i < 500; $i++) {
        $candidate = $base . '-' . $i . '_FBL';
        if (!fbl_fi_folder_id($candidate)) return $candidate;
    }
    return $base . '-' . time() . '_FBL';
}

function fbl_fi_is_image_name($name) {
    return (bool) preg_match('/\.(jpe?g|png|gif|webp|tiff?)$/i', $name);
}

// Reject zip-slip and junk entries.
function fbl_fi_safe_entry($name) {
    if ($name === '') return false;
    if (strpos($name, '..') !== false) return false;
    if (substr($name, 0, 1) === '/') return false;
    if (strpos($name, '__MACOSX') === 0) return false;
    if (strpos(basename($name), '._') === 0) return false;
    return true;
}

function fbl_fi_flush_gallery_cache() {
    if (function_exists('fbl_gallery_flush_cache')) fbl_gallery_flush_cache();
}

/**
 * Resize an image in place to fit within $maxedge on its longest side.
 * Uses Imagick when available, GD otherwise. Returns true or an error string.
 */
function fbl_fi_resize($path, $maxedge, $quality) {
    if (extension_loaded('imagick')) {
        try {
            $im = new Imagick($path);
            $im->autoOrient();
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            if ($w > $maxedge || $h > $maxedge) {
                $im->resizeImage($maxedge, $maxedge, Imagick::FILTER_LANCZOS, 1, true);
            }
            if (strtolower($im->getImageFormat()) === 'jpeg') {
                $im->setImageCompressionQuality($quality);
            }
            $im->stripImage();
            $im->writeImage($path);
            $im->clear();
            $im->destroy();
            return true;
        } catch (Exception $e) {
            return 'imagick: ' . $e->getMessage();
        }
    }

    if (!extension_loaded('gd')) return 'no imagick or gd';

    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) return 'gd: ' . $editor->get_error_message();
    $size = $editor->get_size();
    if ($size['width'] > $maxedge || $size['height'] > $maxedge) {
        $r = $editor->resize($maxedge, $maxedge, false);
        if (is_wp_error($r)) return 'gd: ' . $r->get_error_message();
    }
    $editor->set_quality($quality);
    $saved = $editor->save($path);
    if (is_wp_error($saved)) return 'gd: ' . $saved->get_error_message();
    return true;
}

/**
 * Extract a zip of images into a session dir and return a manifest array,
 * or an error string.
 */
function fbl_fi_extract_zip($zippath, $origname) {
    if (!class_exists('ZipArchive')) return 'ZipArchive not available';

    $zip = new ZipArchive();
    if ($zip->open($zippath) !== true) return 'could not open zip';

    $session = 'imp_' . wp_generate_password(8, false, false);
    $dest    = trailingslashit(fbl_fi_workdir()) . $session;
    wp_mkdir_p($dest);

    $files   = array();
    $topdirs = array();
    $skipped = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!fbl_fi_safe_entry($entry)) { $skipped++; continue; }

        if (substr($entry, -1) === '/') {
            $parts = explode('/', trim($entry, '/'));
            if (!empty($parts[0])) $topdirs[$parts[0]] = true;
            continue;
        }
        if (!fbl_fi_is_image_name($entry)) { $skipped++; continue; }

        if (strpos($entry, '/') !== false) {
            $parts = explode('/', $entry);
            $topdirs[$parts[0]] = true;
        }

        $target = $dest . '/' . basename($entry);
        $n = 1;
        while (file_exists($target)) {
            $target = $dest . '/' . pathinfo(basename($entry), PATHINFO_FILENAME)
                    . '-' . $n . '.' . pathinfo($entry, PATHINFO_EXTENSION);
            $n++;
        }

        $stream = $zip->getStream($entry);
        if (!$stream) { $skipped++; continue; }
        $out = fopen($target, 'wb');
        if ($out) {
            stream_copy_to_stream($stream, $out);
            fclose($out);
            $files[] = basename($target);
        } else {
            $skipped++;
        }
        fclose($stream);
    }
    $zip->close();

    if (empty($files)) return 'no images found in that zip';

    $album = '';
    $tops  = array_keys($topdirs);
    if (count($tops) === 1) $album = $tops[0];
    if ($album === '')      $album = pathinfo($origname, PATHINFO_FILENAME);
    $album = trim(preg_replace('/\s+/', ' ', str_replace(array('_', '-'), ' ', $album)));

    set_transient('fbl_fi_' . $session, array(
        'dir'   => $dest,
        'files' => $files,
    ), 6 * HOUR_IN_SECONDS);

    return array(
        'session' => $session,
        'count'   => count($files),
        'skipped' => $skipped,
        'album'   => $album,
        'folder'  => fbl_fi_unique_folder_name($album),
    );
}

/* ---------------------------------------------------------
   Admin page
   --------------------------------------------------------- */
function fbl_flickr_import_page() {
    if (!current_user_can('upload_files')) return;
    $nonce   = wp_create_nonce('fbl_flickr_import');
    $has_img = extension_loaded('imagick') || extension_loaded('gd');
    $has_zip = class_exists('ZipArchive');
    ?>
    <div class="wrap fbl-fi">
        <h1>Flickr Import</h1>
        <p class="description">
            Upload a Flickr album <code>.zip</code>. Images are resized (not cropped),
            added to the Media Library, and placed in a new FileBird folder named after
            the album with <code>_FBL</code> appended. Crop your images in Flickr before downloading.
        </p>

        <?php if (!$has_zip): ?>
            <div class="notice notice-error"><p>PHP ZipArchive is not available — this tool cannot run.</p></div>
        <?php endif; ?>
        <?php if (!$has_img): ?>
            <div class="notice notice-error"><p>Neither the Imagick nor GD PHP extension is available — images cannot be resized.</p></div>
        <?php elseif (!extension_loaded('imagick')): ?>
            <div class="notice notice-warning"><p>Imagick not available; using GD. Very large images may fail on this server's memory limit.</p></div>
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="fbl-fi-file">Album zip</label></th>
                <td>
                    <input type="file" id="fbl-fi-file" accept=".zip,application/zip">
                    <p class="description">Uploaded in 5&nbsp;MB pieces, so album size is not limited by server upload settings.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fbl-fi-maxedge">Resize longest edge</label></th>
                <td>
                    <input type="number" id="fbl-fi-maxedge" value="2000" min="400" max="6000" step="100" style="width:110px;"> px
                    <p class="description">Images larger than this are scaled down. Smaller images are left alone.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fbl-fi-quality">JPEG quality</label></th>
                <td>
                    <input type="number" id="fbl-fi-quality" value="88" min="60" max="100" step="1" style="width:80px;">
                    <p class="description">88 is a good balance of quality and file size.</p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" class="button button-primary" id="fbl-fi-upload">Upload and inspect</button>
            <span id="fbl-fi-status" class="fbl-fi-status"></span>
        </p>

        <div id="fbl-fi-stage2" style="display:none; background:#fff; border:1px solid #c3c4c7; padding:1rem; max-width:720px;">
            <h2 style="margin-top:0;">Ready to import</h2>
            <p id="fbl-fi-summary"></p>
            <p>
                <label><strong>Destination folder:</strong><br>
                    <input type="text" id="fbl-fi-folder" size="46">
                </label>
                <br><span class="description">Pre-filled from the album name. Edit if you like — it is created if it does not exist.</span>
            </p>
            <p>
                <button type="button" class="button button-primary" id="fbl-fi-start">Start import</button>
                <button type="button" class="button" id="fbl-fi-cancel">Cancel</button>
            </p>
            <div id="fbl-fi-progress-wrap" style="display:none;">
                <div class="fbl-fi-bar"><div class="fbl-fi-bar-fill" id="fbl-fi-bar"></div></div>
                <p id="fbl-fi-progress-text"></p>
                <div id="fbl-fi-log" class="fbl-fi-log"></div>
            </div>
        </div>
    </div>

    <script>
    window.FBL_FI = {
        ajax:  <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
        nonce: <?php echo json_encode($nonce); ?>,
        batch: <?php echo (int) FBL_FI_BATCH; ?>
    };
    </script>
    <?php
}

/* ---------------------------------------------------------
   Enqueue
   --------------------------------------------------------- */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'media_page_fbl-flickr-import') return;
    $theme = get_stylesheet_directory_uri();
    $ver   = wp_get_theme()->get('Version');
    wp_enqueue_style('fbl-flickr-import', $theme . '/css/flickr-import.css', array(), $ver);
    wp_enqueue_script('fbl-flickr-import', $theme . '/js/flickr-import.js', array(), $ver, true);
});

/* ---------------------------------------------------------
   AJAX: receive one chunk of the zip
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_fi_chunk', function () {
    check_ajax_referer('fbl_flickr_import', 'nonce');
    if (!current_user_can('upload_files')) wp_send_json_error('forbidden');

    $uid   = isset($_POST['uid'])   ? preg_replace('/[^a-zA-Z0-9]/', '', wp_unslash($_POST['uid'])) : '';
    $index = isset($_POST['index']) ? (int) $_POST['index'] : -1;
    $total = isset($_POST['total']) ? (int) $_POST['total'] : 0;
    $name  = isset($_POST['name'])  ? sanitize_file_name(wp_unslash($_POST['name'])) : 'album.zip';

    if ($uid === '' || $index < 0 || $total < 1) wp_send_json_error('bad chunk request');
    if (empty($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('chunk upload error');
    }

    $dir = trailingslashit(fbl_fi_workdir()) . 'chunks';
    if (!file_exists($dir)) wp_mkdir_p($dir);
    $part = $dir . '/' . $uid . '.part';

    $mode = ($index === 0) ? 'wb' : 'ab';
    $in   = fopen($_FILES['chunk']['tmp_name'], 'rb');
    $out  = fopen($part, $mode);
    if (!$in || !$out) wp_send_json_error('could not write chunk');
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);

    if ($index + 1 < $total) {
        wp_send_json_success(array('received' => $index + 1, 'total' => $total, 'complete' => false));
    }

    $res = fbl_fi_extract_zip($part, $name);
    @unlink($part);

    if (is_string($res)) wp_send_json_error($res);
    $res['complete'] = true;
    wp_send_json_success($res);
});

/* ---------------------------------------------------------
   AJAX: process one batch of images
   --------------------------------------------------------- */
add_action('wp_ajax_fbl_fi_batch', function () {
    check_ajax_referer('fbl_flickr_import', 'nonce');
    if (!current_user_can('upload_files')) wp_send_json_error('forbidden');

    $session = isset($_POST['session']) ? sanitize_file_name(wp_unslash($_POST['session'])) : '';
    $folder  = isset($_POST['folder'])  ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
    $offset  = isset($_POST['offset'])  ? (int) $_POST['offset'] : 0;
    $maxedge = isset($_POST['maxedge']) ? max(400, min(6000, (int) $_POST['maxedge'])) : 2000;
    $quality = isset($_POST['quality']) ? max(60, min(100, (int) $_POST['quality'])) : 88;

    $state = get_transient('fbl_fi_' . $session);
    if (!$state || empty($state['files'])) wp_send_json_error('session expired — re-upload the zip');
    if ($folder === '') wp_send_json_error('no destination folder');

    $fid = fbl_fi_folder_id($folder);
    if (!$fid) $fid = fbl_fi_create_folder($folder);
    if (!$fid) wp_send_json_error('could not create folder');

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    global $wpdb;
    $table = "{$wpdb->prefix}fbv_attachment_folder";

    $files = $state['files'];
    $total = count($files);
    $slice = array_slice($files, $offset, FBL_FI_BATCH);
    $log   = array();
    $done  = 0;

    foreach ($slice as $fname) {
        $src = trailingslashit($state['dir']) . $fname;
        if (!file_exists($src)) {
            $log[] = array('file' => $fname, 'ok' => false, 'msg' => 'missing');
            continue;
        }

        $rsz = fbl_fi_resize($src, $maxedge, $quality);
        if ($rsz !== true) {
            $log[] = array('file' => $fname, 'ok' => false, 'msg' => 'resize failed: ' . $rsz);
            continue;
        }

        $upload = wp_upload_bits($fname, null, file_get_contents($src));
        if (!empty($upload['error'])) {
            $log[] = array('file' => $fname, 'ok' => false, 'msg' => $upload['error']);
            continue;
        }

        $ftype = wp_check_filetype($upload['file'], null);
        $attach_id = wp_insert_attachment(array(
            'post_mime_type' => $ftype['type'],
            'post_title'     => pathinfo($fname, PATHINFO_FILENAME),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ), $upload['file']);

        if (is_wp_error($attach_id) || !$attach_id) {
            $log[] = array('file' => $fname, 'ok' => false, 'msg' => 'insert failed');
            continue;
        }

        $meta = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $meta);

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (folder_id, attachment_id) VALUES (%d, %d)",
            $fid, $attach_id
        ));

        @unlink($src);
        $done++;
        $log[] = array('file' => $fname, 'ok' => true, 'msg' => 'imported');
    }

    $next = $offset + count($slice);
    $finished = ($next >= $total);

    if ($finished) {
        $dir = $state['dir'];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $leftover) @unlink($leftover);
            @rmdir($dir);
        }
        delete_transient('fbl_fi_' . $session);
        fbl_fi_flush_gallery_cache();
    }

    wp_send_json_success(array(
        'offset'   => $next,
        'total'    => $total,
        'done'     => $done,
        'log'      => $log,
        'finished' => $finished,
        'folder'   => $folder,
    ));
});