<?php
/* =========================================================
   FBL GALLERY - FileBird folder-driven gallery
   [fbl_gallery folder="Cabins" view="grid|carousel|masonry"
                columns="3" limit="0" size="large"
                order="date_desc|date_asc|name|filebird|random"
                shuffle="pageload|daily|weekly|never"
                autoplay="5000" link="lightbox|none"]
   ========================================================= */

/* ---------------------------------------------------------
   Resolve FileBird folder name -> attachment IDs (cached)
   --------------------------------------------------------- */
function fbl_gallery_get_ids($folder_name) {
    global $wpdb;

    $cache_key = 'fbl_gallery_' . md5($folder_name);
    $ids = get_transient($cache_key);

    if ($ids !== false) {
        return $ids;
    }

    $folder_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}fbv WHERE name = %s",
        $folder_name
    ));

    if (!$folder_id) {
        return null; // folder not found (distinct from empty folder)
    }

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT fa.attachment_id
         FROM {$wpdb->prefix}fbv_attachment_folder fa
         INNER JOIN {$wpdb->posts} p ON p.ID = fa.attachment_id
         WHERE fa.folder_id = %d
           AND p.post_type = 'attachment'
           AND p.post_mime_type LIKE 'image/%%'",
        $folder_id
    ));

    $ids = array_map('intval', $ids);
    set_transient($cache_key, $ids, 12 * HOUR_IN_SECONDS);

    return $ids;
}

/* ---------------------------------------------------------
   Cache invalidation - FileBird changes & attachment changes
   --------------------------------------------------------- */
function fbl_gallery_flush_cache() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_fbl_gallery_%'
            OR option_name LIKE '_transient_timeout_fbl_gallery_%'"
    );
}
add_action('fbd_after_set_folder',    'fbl_gallery_flush_cache'); // FileBird: attachment moved
add_action('fbd_after_delete_folder', 'fbl_gallery_flush_cache'); // FileBird: folder deleted
add_action('add_attachment',          'fbl_gallery_flush_cache');
add_action('delete_attachment',       'fbl_gallery_flush_cache');

/* ---------------------------------------------------------
   Ordering
   --------------------------------------------------------- */
function fbl_gallery_order_ids($ids, $order, $shuffle, $folder_name) {
    if ($order === 'random') {
        $tz  = new DateTimeZone('America/Toronto');
        $now = new DateTime('now', $tz);

        switch ($shuffle) {
            case 'daily':
                mt_srand(crc32($folder_name . $now->format('Y-m-d')));
                shuffle($ids);
                mt_srand();
                break;
            case 'weekly':
                mt_srand(crc32($folder_name . $now->format('o-W')));
                shuffle($ids);
                mt_srand();
                break;
            case 'never':
                mt_srand(crc32($folder_name));
                shuffle($ids);
                mt_srand();
                break;
            case 'pageload':
            default:
                shuffle($ids);
                break;
        }
        return $ids;
    }

    if ($order === 'filebird') {
        return $ids; // FileBird join order as-is
    }

    // date/name orders need post data
    $posts = get_posts(array(
        'post_type'      => 'attachment',
        'post__in'       => $ids,
        'posts_per_page' => -1,
        'orderby'        => ($order === 'name') ? 'title' : 'date',
        'order'          => ($order === 'date_asc') ? 'ASC' : (($order === 'name') ? 'ASC' : 'DESC'),
    ));

    return wp_list_pluck($posts, 'ID');
}

/* ---------------------------------------------------------
   Shortcode
   --------------------------------------------------------- */
add_shortcode('fbl_gallery', function($atts) {
    $atts = shortcode_atts(array(
        'folder'   => '',
        'view'     => 'grid',
        'columns'  => 3,
        'limit'    => 0,
        'size'     => 'large',
        'order'    => 'date_desc',
        'shuffle'  => 'pageload',
        'autoplay' => 5000,
        'link'     => 'lightbox',
        'caption'  => 'caption',
        'thumb_caption' => 'none',
    ), $atts, 'fbl_gallery');

    $folder = trim($atts['folder']);
    if ($folder === '') {
        return current_user_can('edit_posts')
            ? '<p class="fbl-gallery-error">[fbl_gallery] requires a folder attribute.</p>'
            : '';
    }

    $ids = fbl_gallery_get_ids($folder);

    if ($ids === null) {
        return current_user_can('edit_posts')
            ? '<p class="fbl-gallery-error">[fbl_gallery] FileBird folder "' . esc_html($folder) . '" not found.</p>'
            : '';
    }

    if (empty($ids)) {
        return current_user_can('edit_posts')
            ? '<p class="fbl-gallery-error">[fbl_gallery] Folder "' . esc_html($folder) . '" contains no images.</p>'
            : '';
    }

    $ids = fbl_gallery_order_ids($ids, $atts['order'], $atts['shuffle'], $folder);

    $limit = (int) $atts['limit'];
    if ($limit > 0) {
        $ids = array_slice($ids, 0, $limit);
    }

    $view      = in_array($atts['view'], array('grid', 'carousel', 'masonry'), true) ? $atts['view'] : 'grid';
    $columns   = max(1, min(6, (int) $atts['columns']));
    $lightbox  = ($atts['link'] === 'lightbox');
    $group     = 'fbl-' . sanitize_title($folder);
    $autoplay  = max(1000, (int) $atts['autoplay']);
    $cap_mode  = in_array($atts['caption'], array('caption', 'title', 'none'), true) ? $atts['caption'] : 'caption';
    $tcap_mode = in_array($atts['thumb_caption'], array('none', 'caption', 'title'), true) ? $atts['thumb_caption'] : 'none';

    ob_start();
    ?>
    <div class="fbl-gallery fbl-gallery--<?php echo esc_attr($view); ?>"
         style="--fbl-gallery-columns: <?php echo esc_attr($columns); ?>;"
         <?php if ($view === 'carousel'): ?>data-fbl-autoplay="<?php echo esc_attr($autoplay); ?>"<?php endif; ?>>

        <?php foreach ($ids as $i => $id):
            $full  = wp_get_attachment_image_url($id, 'full');
            $thumb = wp_get_attachment_image($id, $atts['size'], false, array(
                'class'   => 'fbl-gallery-image',
                'loading' => ($view === 'carousel' && $i === 0) ? 'eager' : 'lazy',
            ));
            if (!$full || !$thumb) continue;

            $caption = '';
            if ($cap_mode === 'caption') {
                $caption = wp_get_attachment_caption($id);
            } elseif ($cap_mode === 'title') {
                $caption = wp_get_attachment_caption($id);
                if (!$caption) $caption = get_the_title($id);
            }

            $tcaption = '';
            if ($tcap_mode === 'caption') {
                $tcaption = wp_get_attachment_caption($id);
            } elseif ($tcap_mode === 'title') {
                $tcaption = wp_get_attachment_caption($id);
                if (!$tcaption) $tcaption = get_the_title($id);
            }
        ?>
            <div class="fbl-gallery-item<?php echo ($view === 'carousel' && $i === 0) ? ' is-active' : ''; ?>">
                <?php if ($lightbox): ?>
                    <a href="<?php echo esc_url($full); ?>"
                       class="fbl-gallery-link"
                       data-fancybox="<?php echo esc_attr($group); ?>"
                       <?php if ($caption): ?>data-caption="<?php echo esc_attr($caption); ?>"<?php endif; ?>>
                        <?php echo $thumb; ?>
                    </a>
                    <?php else: ?>
                    <?php echo $thumb; ?>
                <?php endif; ?>
                <?php if ($tcaption): ?>
                    <div class="fbl-gallery-caption"><?php echo esc_html($tcaption); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($view === 'carousel' && count($ids) > 1): ?>
            <button type="button" class="fbl-gallery-prev" aria-label="Previous image">&#10094;</button>
            <button type="button" class="fbl-gallery-next" aria-label="Next image">&#10095;</button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
