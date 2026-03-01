<?php
/**
 * FBL Color Tweaker - Editor-Accessible Admin Page
 * Simplified interface for Editors to tweak theme colors
 */

// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'FBL Color Tweaker',           // Page title
        'FBL Color Tweaker',           // Menu title
        'edit_pages',                  // Capability (Editors have this)
        'fbl-color-tweaker',           // Menu slug
        'fbl_color_tweaker_admin_page', // Callback function
        'dashicons-art',               // Icon
        31                             // Position (after FBL Skin)
    );
});

function fbl_color_tweaker_admin_page() {
    global $fbl_color_tweaker;
    
    // Handle Brand theme copy
    if (isset($_POST['fbl_brand_copy_submit']) && check_admin_referer('fbl_brand_copy', 'fbl_brand_nonce')) {
        $source_theme = sanitize_text_field($_POST['copy_from_theme']);
        $source_vars = $fbl_color_tweaker->parse_theme_variables($source_theme);
        
        // Copy all colors from source theme to Brand theme profile
        foreach ($source_vars as $var_key => $var_data) {
            if ($var_data['type'] === 'color') {
                $fbl_color_tweaker->save_to_profile('fbl-skin-brand', $var_key, $var_data['factory_default']);
            }
        }
        
        echo '<div class="notice notice-success"><p>Copied colors from ' . esc_html($fbl_color_tweaker->get_themes()[$source_theme]) . ' to Brand Theme!</p></div>';
    }
    
    // Handle form submission
    if (isset($_POST['fbl_tweaker_save']) && check_admin_referer('fbl_tweaker_save', 'fbl_tweaker_nonce')) {
        // Save all color values to profile
        $current_skin = get_option('fbl_skin', 'fbl-skin-classic');
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'fbl_color_') === 0) {
                $var_name = str_replace('fbl_color_', '--fbl-', $key);
                $fbl_color_tweaker->save_to_profile($current_skin, $var_name, sanitize_text_field($value));
            }
        }
        
        echo '<div class="notice notice-success"><p>Colors saved to Current Profile!</p></div>';
    }
    
    // Handle set factory as current profile
    if (isset($_POST['fbl_tweaker_set_factory_as_profile']) && check_admin_referer('fbl_tweaker_set_factory', 'fbl_tweaker_nonce')) {
        $current_skin = get_option('fbl_skin', 'fbl-skin-classic');
        $theme_vars = $fbl_color_tweaker->parse_theme_variables($current_skin);
        
        // Save all factory defaults as current profile
        foreach ($theme_vars as $var_key => $var_data) {
            if ($var_data['type'] === 'color') {
                $fbl_color_tweaker->save_to_profile($current_skin, $var_key, $var_data['factory_default']);
            }
        }
        
        echo '<div class="notice notice-success"><p>Factory defaults saved as Current Profile!</p></div>';
    }
    
    // Handle reset to factory
    if (isset($_POST['fbl_tweaker_reset_factory']) && check_admin_referer('fbl_tweaker_reset', 'fbl_tweaker_nonce')) {
        $current_skin = get_option('fbl_skin', 'fbl-skin-classic');
        $theme_vars = $fbl_color_tweaker->parse_theme_variables($current_skin);
        
        // Delete all saved profiles (reset to factory)
        foreach ($theme_vars as $var_key => $var_data) {
            $option_name = 'fbl_tweaker_' . $current_skin . '_' . str_replace('--fbl-', '', $var_key);
            delete_option($option_name);
        }
        
        echo '<div class="notice notice-success"><p>Reset to Factory Defaults!</p></div>';
    }
    
    // Get current skin and its variables
    $current_skin = get_option('fbl_skin', 'fbl-skin-classic');
    $current_skin_name = $fbl_color_tweaker->get_themes()[$current_skin];
    $theme_vars = $fbl_color_tweaker->parse_theme_variables($current_skin);
    
    ?>
    <div class="wrap">
        <h1>FBL Color Tweaker</h1>
        <p>Currently editing: <strong><?php echo esc_html($current_skin_name); ?></strong></p>
        <p><strong>Current Profile:</strong> Your saved customizations for this theme. These override the factory defaults.</p>
        <p>Adjust colors below. Click "Save to Current Profile" to keep your changes, or "Reset All to Factory Defaults" to restore original colors.</p>
        
        <?php
        // Show if current profile has any customizations
        $has_customizations = false;
        foreach ($theme_vars as $var_key => $var_data) {
            if ($fbl_color_tweaker->get_current_profile($current_skin, $var_key)) {
                $has_customizations = true;
                break;
            }
        }
        
        if ($has_customizations) {
            echo '<div class="notice notice-info inline"><p>✓ This theme has customizations saved to Current Profile</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>Using factory defaults (no customizations saved)</p></div>';
        }
        ?>
        
        <?php if ($current_skin === 'fbl-skin-brand'): ?>
        <div class="notice notice-info">
            <h3>Brand Theme - Custom Colors</h3>
            <p>The Brand theme is your custom theme. You can start by copying colors from any existing theme, then customize freely.</p>
            
            <form method="post">
                <?php wp_nonce_field('fbl_brand_copy', 'fbl_brand_nonce'); ?>
                <p>
                    <label for="copy_from_theme"><strong>Copy colors from:</strong></label>
                    <select name="copy_from_theme" id="copy_from_theme">
                        <?php foreach ($fbl_color_tweaker->get_themes() as $slug => $name): ?>
                            <?php if ($slug !== 'fbl-skin-brand'): ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" name="fbl_brand_copy_submit" class="button" value="Copy Colors to Brand Theme">
                </p>
            </form>
        </div>
        <?php endif; ?>

        <form method="post" id="fbl-tweaker-form">
            <?php wp_nonce_field('fbl_tweaker_save', 'fbl_tweaker_nonce'); ?>
            
            <table class="form-table">
                <?php foreach ($theme_vars as $var_key => $var_data): 
                    if ($var_data['type'] !== 'color') continue; // Only show colors for now
                    
                    $current_profile = $fbl_color_tweaker->get_current_profile($current_skin, $var_key);
                    $current_value = $current_profile ? $current_profile : $var_data['factory_default'];
                    $field_name = 'fbl_color_' . $var_data['name'];
                ?>
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr($field_name); ?>">
                            <?php echo esc_html($var_data['label']); ?>
                        </label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            id="<?php echo esc_attr($field_name); ?>"
                            name="<?php echo esc_attr($field_name); ?>"
                            value="<?php echo esc_attr($current_value); ?>"
                            class="fbl-color-picker"
                            data-default-color="<?php echo esc_attr($var_data['factory_default']); ?>"
                        />
                        <p class="description">
                            Factory: <?php echo esc_html($var_data['factory_default']); ?>
                            <?php if ($current_profile): ?>
                                | Profile: <?php echo esc_html($current_profile); ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <p class="submit">
                <input type="submit" name="fbl_tweaker_save" class="button button-primary" value="Save to Current Profile">
            </p>
        </form>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('fbl_tweaker_set_factory', 'fbl_tweaker_nonce'); ?>
            <p class="submit">
                <input 
                    type="submit" 
                    name="fbl_tweaker_set_factory_as_profile" 
                    class="button button-secondary" 
                    value="Set Factory as Current Profile"
                    onclick="return confirm('This will overwrite your Current Profile with factory defaults. Are you sure?');"
                >
            </p>
            <p class="description">Use this to reset your baseline to factory defaults. Any current tweaks will be replaced with the original theme colors.</p>
        </form>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('fbl_tweaker_reset', 'fbl_tweaker_nonce'); ?>
            <p class="submit">
                <input 
                    type="submit" 
                    name="fbl_tweaker_reset_factory" 
                    class="button button-secondary" 
                    value="Reset All to Factory Defaults"
                    onclick="return confirm('Are you sure? This will delete all your saved color tweaks and restore factory defaults.');"
                >
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Initialize WordPress color pickers
        $('.fbl-color-picker').wpColorPicker();
    });
    </script>
    <?php
}

// Enqueue color picker
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_fbl-color-tweaker') {
        return;
    }
    
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
});
// Hide Appearance items from admin bar for editors
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (current_user_can('manage_options')) return;
    
    $wp_admin_bar->remove_node('widgets');
    $wp_admin_bar->remove_node('menus');
}, 999);