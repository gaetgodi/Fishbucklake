<?php
/* =========================================================
   CATCH OF THE DAY - ADMIN CAPABILITIES
   ========================================================= */

add_action('admin_init', function() {
    $editor = get_role('editor');
    $admin  = get_role('administrator');

    if ($editor) $editor->add_cap('manage_catch_images');
    if ($admin)  $admin->add_cap('manage_catch_images');
});

/* =========================================================
   CATCH OF THE DAY - ADMIN MENU
   ========================================================= */

add_action('admin_menu', function() {
    add_menu_page(
        'Catch of the Day Images',
        'Catch Images',
        'manage_catch_images',
        'fbl-catch-images',
        'fbl_catch_images_page',
        'dashicons-images-alt2',
        31
    );
});

/* =========================================================
   CATCH OF THE DAY - ADMIN PAGE
   ========================================================= */

function fbl_catch_images_page() {
    $upload_dir = wp_upload_dir();
    $catch_dir  = $upload_dir['basedir'] . '/feature-images';
    $catch_url  = $upload_dir['baseurl'] . '/feature-images';

    if (!file_exists($catch_dir)) {
        wp_mkdir_p($catch_dir);
    }

    // Handle bulk deletion
    if (isset($_POST['fbl_catch_bulk_delete']) && check_admin_referer('fbl_catch_bulk_delete', 'fbl_catch_bulk_nonce')) {
        if (!empty($_POST['fbl_catch_files'])) {
            $deleted_count = 0;
            foreach ($_POST['fbl_catch_files'] as $filename) {
                $filename  = sanitize_file_name($filename);
                $file_path = $catch_dir . '/' . $filename;

                if (file_exists($file_path) && preg_match('/^day-(0[1-9]|[12][0-9]|3[01])\.jpg$/', $filename)) {
                    unlink($file_path);
                    $deleted_count++;
                }
            }

            if ($deleted_count > 0) {
                echo '<div class="notice notice-success"><p>' . $deleted_count . ' image(s) deleted successfully!</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>No images selected for deletion.</p></div>';
        }
    }

    // Handle file upload
    if (isset($_POST['fbl_catch_upload']) && check_admin_referer('fbl_catch_upload', 'fbl_catch_nonce')) {
        if (!empty($_FILES['fbl_catch_files']['name'][0])) {
            $files          = $_FILES['fbl_catch_files'];
            $uploaded_count = 0;

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === 0) {
                    $filename    = sanitize_file_name($files['name'][$i]);
                    $destination = $catch_dir . '/' . $filename;

                    if (preg_match('/^day-(0[1-9]|[12][0-9]|3[01])\.jpg$/', $filename)) {
                        if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                            chmod($destination, 0644);
                            $uploaded_count++;
                        }
                    }
                }
            }

            if ($uploaded_count > 0) {
                echo '<div class="notice notice-success"><p>' . $uploaded_count . ' image(s) uploaded successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>No valid images uploaded. Please name files day-01.jpg through day-31.jpg</p></div>';
            }
        }
    }

    // Handle single file deletion
    if (isset($_POST['fbl_catch_delete']) && check_admin_referer('fbl_catch_delete', 'fbl_catch_delete_nonce')) {
        $file_to_delete = sanitize_file_name($_POST['fbl_catch_delete']);
        $file_path      = $catch_dir . '/' . $file_to_delete;

        if (file_exists($file_path) && preg_match('/^day-(0[1-9]|[12][0-9]|3[01])\.jpg$/', $file_to_delete)) {
            unlink($file_path);
            echo '<div class="notice notice-success"><p>Image deleted successfully!</p></div>';
        }
    }

    // Get existing images
    $existing_images = array();
    if (is_dir($catch_dir)) {
        $files = scandir($catch_dir);
        foreach ($files as $file) {
            if (preg_match('/^day-(0[1-9]|[12][0-9]|3[01])\.jpg$/', $file)) {
                $existing_images[] = $file;
            }
        }
        sort($existing_images);
    }

    $view_mode = isset($_GET['view']) ? $_GET['view'] : 'grid';

    ?>
    <div class="wrap">
        <h1>Catch of the Day Images</h1>
        <p>Upload images for the daily feature. Images must be named <strong>day-01.jpg</strong> through <strong>day-31.jpg</strong></p>
        <p><strong>Requirements:</strong> JPG format, 1920px wide, 72 DPI recommended</p>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin: 20px 0;">
            <h2>Upload Images</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('fbl_catch_upload', 'fbl_catch_nonce'); ?>
                <p>
                    <input type="file" name="fbl_catch_files[]" multiple accept=".jpg,.jpeg" style="margin-bottom: 10px;">
                </p>
                <p class="description">Select one or more images. Files must be named day-01.jpg, day-02.jpg, etc.</p>
                <p>
                    <button type="submit" name="fbl_catch_upload" class="button button-primary">Upload Images</button>
                </p>
            </form>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccc;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Current Images (<?php echo count($existing_images); ?>/31)</h2>
                <div>
                    <a href="?page=fbl-catch-images&view=grid" class="button <?php echo $view_mode === 'grid' ? 'button-primary' : ''; ?>">Grid View</a>
                    <a href="?page=fbl-catch-images&view=list" class="button <?php echo $view_mode === 'list' ? 'button-primary' : ''; ?>">List View</a>
                </div>
            </div>

            <?php if (empty($existing_images)): ?>
                <p>No images uploaded yet.</p>
            <?php else: ?>

                <form method="post" id="fbl-bulk-form">
                    <?php wp_nonce_field('fbl_catch_bulk_delete', 'fbl_catch_bulk_nonce'); ?>

                    <div style="margin-bottom: 15px;">
                        <button type="button" id="fbl-select-all" class="button">Select All</button>
                        <button type="button" id="fbl-deselect-all" class="button">Deselect All</button>
                        <button type="submit" name="fbl_catch_bulk_delete" class="button button-primary"
                                style="background: #dc3232; border-color: #dc3232;"
                                onclick="return confirm('Delete selected images? This cannot be undone.');">
                            Delete Selected
                        </button>
                        <span id="fbl-selected-count" style="margin-left: 10px; font-weight: bold;"></span>
                    </div>

                    <?php if ($view_mode === 'grid'): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                            <?php foreach ($existing_images as $image): ?>
                                <?php
                                $day_number = str_replace(array('day-', '.jpg'), '', $image);
                                $image_url  = $catch_url . '/' . $image;
                                ?>
                                <div style="border: 2px solid #ddd; border-radius: 8px; overflow: hidden; background: #f9f9f9;">
                                    <div style="position: relative;">
                                        <img src="<?php echo esc_url($image_url); ?>"
                                             style="width: 100%; height: 150px; object-fit: cover; display: block;">
                                        <div style="position: absolute; top: 10px; left: 10px;">
                                            <input type="checkbox" name="fbl_catch_files[]"
                                                   value="<?php echo esc_attr($image); ?>"
                                                   class="fbl-image-checkbox"
                                                   style="width: 20px; height: 20px; cursor: pointer;">
                                        </div>
                                        <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold;">
                                            Day <?php echo intval($day_number); ?>
                                        </div>
                                    </div>
                                    <div style="padding: 10px; text-align: center;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 8px;"><?php echo esc_html($image); ?></div>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('fbl_catch_delete', 'fbl_catch_delete_nonce'); ?>
                                            <input type="hidden" name="fbl_catch_delete" value="<?php echo esc_attr($image); ?>">
                                            <button type="submit" class="button button-small"
                                                    onclick="return confirm('Delete this image?');"
                                                    style="font-size: 11px;">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="fbl-select-all-table" style="cursor: pointer;">
                                    </th>
                                    <th style="width: 80px;">Day</th>
                                    <th>Preview</th>
                                    <th>Filename</th>
                                    <th style="width: 100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_images as $image): ?>
                                    <?php
                                    $day_number = str_replace(array('day-', '.jpg'), '', $image);
                                    $image_url  = $catch_url . '/' . $image;
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="fbl_catch_files[]"
                                                   value="<?php echo esc_attr($image); ?>"
                                                   class="fbl-image-checkbox"
                                                   style="cursor: pointer;">
                                        </td>
                                        <td><strong>Day <?php echo intval($day_number); ?></strong></td>
                                        <td>
                                            <img src="<?php echo esc_url($image_url); ?>"
                                                 style="max-width: 200px; height: auto; display: block;">
                                        </td>
                                        <td><?php echo esc_html($image); ?></td>
                                        <td>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('fbl_catch_delete', 'fbl_catch_delete_nonce'); ?>
                                                <input type="hidden" name="fbl_catch_delete" value="<?php echo esc_attr($image); ?>">
                                                <button type="submit" class="button button-small"
                                                        onclick="return confirm('Delete this image?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </form>

                <script>
                jQuery(document).ready(function($) {
                    $('#fbl-select-all, #fbl-select-all-table').on('click', function() {
                        $('.fbl-image-checkbox').prop('checked', true);
                        updateSelectedCount();
                    });

                    $('#fbl-deselect-all').on('click', function() {
                        $('.fbl-image-checkbox').prop('checked', false);
                        updateSelectedCount();
                    });

                    $('.fbl-image-checkbox').on('change', updateSelectedCount);

                    function updateSelectedCount() {
                        var count = $('.fbl-image-checkbox:checked').length;
                        $('#fbl-selected-count').text(count > 0 ? count + ' image(s) selected' : '');
                    }

                    $('#fbl-bulk-form').on('submit', function(e) {
                        if ($('.fbl-image-checkbox:checked').length === 0) {
                            e.preventDefault();
                            alert('Please select at least one image to delete.');
                            return false;
                        }
                    });
                });
                </script>

            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* =========================================================
   CATCH GALLERY - SHORTCODE
   ========================================================= */

add_shortcode('catch_gallery', function() {
    $upload_dir = wp_upload_dir();
    $catch_dir  = $upload_dir['basedir'] . '/feature-images';
    $catch_url  = $upload_dir['baseurl'] . '/feature-images';

    // Use explicit Eastern Time to avoid server UTC timezone affecting day rollover
    $tz            = new DateTimeZone('America/Toronto');
    $now           = new DateTime('now', $tz);
    $current_day   = (int) $now->format('j');
    $current_year  = $now->format('Y');
    $current_month = $now->format('m');

    $images = array();
    for ($day = 1; $day <= $current_day; $day++) {
        $filename = 'day-' . sprintf('%02d', $day) . '.jpg';
        $filepath = $catch_dir . '/' . $filename;

        if (file_exists($filepath)) {
            $date_string = $current_year . '-' . $current_month . '-' . sprintf('%02d', $day);

            $images[] = array(
                'day'  => $day,
                'url'  => $catch_url . '/' . $filename,
                'date' => date_i18n('F j, Y', strtotime($date_string))
            );
        }
    }

    $images = array_reverse($images);

    ob_start();
    ?>

    <div class="fbl-catch-gallery-header">
        <h1 class="fbl-catch-gallery-title">Catch of the Day Gallery</h1>
        <p class="fbl-catch-gallery-subtitle">
            <?php echo date_i18n('F Y'); ?> • <?php echo count($images); ?> catches
        </p>
    </div>

    <?php if (empty($images)): ?>
        <div class="fbl-catch-gallery-empty">
            <p>No catches uploaded yet for this month. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="fbl-catch-gallery-grid">
            <?php foreach ($images as $image): ?>
                <div class="fbl-catch-gallery-item">
                    <a href="<?php echo esc_url($image['url']); ?>"
                       class="fbl-catch-gallery-link"
                       data-fancybox="gallery"
                       data-caption="Displayed on <?php echo esc_attr($image['date']); ?>">
                        <img src="<?php echo esc_url($image['url']); ?>"
                             loading="lazy"
                             alt="Catch of Day <?php echo $image['day']; ?>"
                             class="fbl-catch-gallery-image">
                        <div class="fbl-catch-gallery-overlay">
                            <span class="fbl-catch-gallery-day">Day <?php echo $image['day']; ?></span>
                            <span class="fbl-catch-gallery-date"><?php echo date_i18n('M j', strtotime($image['date'])); ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
});