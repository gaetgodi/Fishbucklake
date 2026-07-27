<?php
/* =========================================================
   FBL FOLDER USAGE REPORT
   Media > Folder Usage

   One glance answer to "which folders are used where?" —
   every FileBird folder, its image count, and every page
   that references it via [fbl_gallery], with the exact
   shortcode used on each.

   Read-only. Reuses the same shortcode-scanning logic as
   Gallery Builder's "Find Pages" feature (inc/gallery-builder.php),
   just run across every folder instead of one at a time.
   ========================================================= */

if (!defined('ABSPATH')) exit;

add_action('admin_init', function() {
    $editor = get_role('editor');
    $admin  = get_role('administrator');
    if ($editor) $editor->add_cap('manage_fbl_folder_usage');
    if ($admin)  $admin->add_cap('manage_fbl_folder_usage');
});

add_action('admin_menu', function() {
    add_media_page(
        'Folder Usage',
        'Folder Usage',
        'manage_fbl_folder_usage',
        'fbl-folder-usage',
        'fbl_folder_usage_page'
    );
});

/**
 * Every FileBird folder, sorted WEB_ first, then _FBL, then the rest.
 */
function fbl_fu_all_folders() {
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

    usort($rows, function ($a, $b) {
        $rank = function ($n) {
            if (strpos($n, 'WEB_') === 0)  return 0;
            if (substr($n, -4) === '_FBL') return 1;
            return 2;
        };
        $ra = $rank($a->name);
        $rb = $rank($b->name);
        if ($ra !== $rb) return $ra - $rb;
        return strcasecmp($a->name, $b->name);
    });
    return $rows;
}

/**
 * Every page/post containing at least one [fbl_gallery ...] shortcode,
 * mapped by the folder= attribute of each occurrence.
 * Reuses fbl_gb_find_shortcodes_in_content() from gallery-builder.php
 * when available; falls back to a local copy otherwise so this page
 * works even if load order changes.
 */
function fbl_fu_scan_all_pages() {
    global $wpdb;

    $posts = $wpdb->get_results(
        "SELECT ID, post_title, post_status, post_content
         FROM {$wpdb->posts}
         WHERE post_content LIKE '%fbl_gallery%'
           AND post_type IN ('page', 'post')
           AND post_status IN ('publish', 'draft', 'private', 'pending')"
    );

    $by_folder = array(); // folder name => array of instances

    foreach ($posts as $p) {
        $found = function_exists('fbl_gb_find_shortcodes_in_content')
            ? fbl_gb_find_shortcodes_in_content($p->post_content)
            : fbl_fu_find_shortcodes_fallback($p->post_content);

        foreach ($found as $sc) {
            if ($sc['folder'] === '') continue;
            $by_folder[$sc['folder']][] = array(
                'post_id'    => (int) $p->ID,
                'post_title' => $p->post_title ?: '(untitled)',
                'status'     => $p->post_status,
                'decoded'    => $sc['decoded'],
                'view_url'   => get_permalink($p->ID),
            );
        }
    }

    return $by_folder;
}

/**
 * Local fallback shortcode-finder (mirrors gallery-builder.php's version)
 * in case this page ever loads before that file.
 */
function fbl_fu_find_shortcodes_fallback($content) {
    $results = array();
    if (!preg_match_all('/\[fbl_gallery\b[^\]]*\]/', $content, $m)) {
        return $results;
    }
    foreach ($m[0] as $raw) {
        $token = '"';
        if (strpos($raw, '\\\\u0022') !== false) {
            $token = '\\\\u0022';
        } elseif (strpos($raw, '\\u0022') !== false) {
            $token = '\\u0022';
        } elseif (strpos($raw, '\\"') !== false) {
            $token = '\\"';
        }
        $decoded = ($token === '"') ? $raw : str_replace($token, '"', $raw);
        $folder = '';
        if (preg_match('/folder="([^"]*)"/', $decoded, $fm)) {
            $folder = $fm[1];
        }
        $results[] = array('raw' => $raw, 'decoded' => $decoded, 'folder' => $folder);
    }
    return $results;
}

function fbl_folder_usage_page() {
    $folders   = fbl_fu_all_folders();
    $usage_map = fbl_fu_scan_all_pages();

    // Re-sort: used folders first (alphabetically by the page title(s) that
    // use them), then unused folders at the bottom (alphabetically by name).
    usort($folders, function ($a, $b) use ($usage_map) {
        $uses_a = isset($usage_map[$a->name]) ? $usage_map[$a->name] : array();
        $uses_b = isset($usage_map[$b->name]) ? $usage_map[$b->name] : array();

        $used_a = !empty($uses_a);
        $used_b = !empty($uses_b);

        if ($used_a !== $used_b) {
            return $used_a ? -1 : 1; // used folders first
        }

        if ($used_a) {
            // sort by the alphabetically-first page title among this folder's uses
            $titles_a = array_map(function ($u) { return strtolower($u['post_title']); }, $uses_a);
            $titles_b = array_map(function ($u) { return strtolower($u['post_title']); }, $uses_b);
            sort($titles_a);
            sort($titles_b);
            return strcmp($titles_a[0], $titles_b[0]);
        }

        // both unused: alphabetical by folder name
        return strcasecmp($a->name, $b->name);
    });

    $total_folders = count($folders);
    $used_count    = 0;
    foreach ($folders as $f) {
        if (!empty($usage_map[$f->name])) $used_count++;
    }
    $unused_count = $total_folders - $used_count;
    ?>
    <div class="wrap fbl-fu">
        <h1>Folder Usage</h1>
        <p class="description">
            Every folder in the Media Library, and every page that displays it via a gallery.
            Use this to check where a folder's photos actually appear before you move, rename,
            or empty it &mdash; and to spot folders nothing currently uses.
        </p>

        <div class="fbl-fu-summary">
            <span><strong><?php echo (int) $total_folders; ?></strong> folders</span>
            <span class="fbl-fu-summary-used"><strong><?php echo (int) $used_count; ?></strong> used on a page</span>
            <span class="fbl-fu-summary-unused"><strong><?php echo (int) $unused_count; ?></strong> not currently used</span>
        </div>

        <p>
            <input type="text" id="fbl-fu-filter" placeholder="Filter folders by name&hellip;" class="regular-text">
        </p>

        <?php if (empty($folders)): ?>
            <div class="notice notice-warning"><p>No FileBird folders found.</p></div>
        <?php else: ?>
            <table class="widefat striped fbl-fu-table">
                <thead>
                    <tr>
                        <th style="width:26%;">Folder</th>
                        <th style="width:8%;">Images</th>
                        <th>Used on</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($folders as $f):
                        $uses = isset($usage_map[$f->name]) ? $usage_map[$f->name] : array();
                    ?>
                        <tr class="fbl-fu-row" data-name="<?php echo esc_attr(strtolower($f->name)); ?>">
                            <td>
                                <strong><?php echo esc_html($f->name); ?></strong>
                            </td>
                            <td><?php echo (int) $f->cnt; ?></td>
                            <td>
                                <?php if (empty($uses)): ?>
                                    <span class="fbl-fu-unused">Not currently used on any page.</span>
                                <?php else: ?>
                                    <ul class="fbl-fu-uses">
                                        <?php foreach ($uses as $u): ?>
                                            <li>
                                                <a href="<?php echo esc_url($u['view_url']); ?>" target="_blank">
                                                    <?php echo esc_html($u['post_title']); ?>
                                                </a>
                                                <?php if ($u['status'] !== 'publish'): ?>
                                                    <span class="fbl-fu-status">(<?php echo esc_html($u['status']); ?>)</span>
                                                <?php endif; ?>
                                                <code class="fbl-fu-shortcode"><?php echo esc_html($u['decoded']); ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        var input = document.getElementById('fbl-fu-filter');
        if (!input) return;
        input.addEventListener('input', function() {
            var q = input.value.toLowerCase();
            document.querySelectorAll('.fbl-fu-row').forEach(function(row) {
                row.style.display = row.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none';
            });
        });
    })();
    </script>
    <?php
}

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'media_page_fbl-folder-usage') return;
    ?>
    <style>
        .fbl-fu-summary { display: flex; gap: 24px; margin: 12px 0 16px; font-size: 14px; }
        .fbl-fu-summary-used strong { color: #00a32a; }
        .fbl-fu-summary-unused strong { color: #996800; }
        .fbl-fu-unused { color: #996800; font-style: italic; }
        .fbl-fu-uses { margin: 0; padding: 0; list-style: none; }
        .fbl-fu-uses li { margin-bottom: 10px; }
        .fbl-fu-uses li:last-child { margin-bottom: 0; }
        .fbl-fu-status { color: #996800; font-size: 12px; margin-left: 4px; }
        .fbl-fu-shortcode {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            background: #f0f0f1;
            padding: 4px 8px;
            border-radius: 3px;
            word-break: break-all;
        }
        .fbl-fu-table td { vertical-align: top; padding-top: 12px; padding-bottom: 12px; }
    </style>
    <?php
}, 20);
