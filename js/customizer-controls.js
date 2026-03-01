(function($) {
    'use strict';

    var currentSkin = null;
    var pendingSkin = null;
    var ajaxUrl = fblCustomizer.ajaxUrl;
    var skinNames = fblCustomizer.skins;

    function showSkinControls(skin) {
        // Hide all skin color controls
        $.each(skinNames, function(skinSlug) {
            var prefix = 'fbl_theme_' + skinSlug.replace(/-/g, '_') + '_';
            $('[id^="customize-control-' + prefix + '"]').hide();
        });

        // Show only the active skin's controls
        var activePrefix = 'fbl_theme_' + skin.replace(/-/g, '_') + '_';
        $('[id^="customize-control-' + activePrefix + '"]').show();

        // Always keep the skin picker visible
        $('#customize-control-fbl_skin_picker').show();

        // Inject or update the "Customizing: Skin Name" label
        var skinName = skinNames[skin] || skin;
        var $section = $('#sub-accordion-section-fbl_theme_colors');
        var $label = $section.find('.fbl-active-skin-label');
        if (!$label.length) {
            $label = $('<p class="fbl-active-skin-label" style="padding: 8px 15px 0; font-style: italic; color: #555; margin: 0;"></p>');
            $section.find('.customize-section-description-container').append($label);
        }
        $label.html('Customizing: <strong>' + skinName + '</strong>');

        // Inject or update the Reset button
        var $reset = $section.find('.fbl-reset-skin-btn');
        if (!$reset.length) {
            $reset = $('<button class="fbl-reset-skin-btn button" style="margin: 8px 15px 4px; background: #a00; color: #fff; border-color: #800;">Reset Skin to Factory Defaults</button>');
            $section.find('.customize-section-description-container').append($reset);

            $reset.on('click', function(e) {
                e.preventDefault();
                var skinName = skinNames[currentSkin] || currentSkin;
                if (!confirm('Reset "' + skinName + '" to factory defaults?\n\nAll saved customizations for this skin will be permanently deleted.')) {
                    return;
                }

                $reset.text('Resetting...').prop('disabled', true);

                $.post(ajaxUrl, {
                    action:  'fbl_reset_skin',
                    skin:    currentSkin,
                    nonce:   fblCustomizer.resetNonce,
                }, function(response) {
                    if (response.success) {
                        // Reload the Customizer to reflect factory defaults
                        window.location.reload();
                    } else {
                        alert('Reset failed: ' + (response.data || 'Unknown error'));
                        $reset.text('Reset Skin to Factory Defaults').prop('disabled', false);
                    }
                }).fail(function() {
                    alert('Reset failed — server error.');
                    $reset.text('Reset Skin to Factory Defaults').prop('disabled', false);
                });
            });
        }
    }

    wp.customize.bind('ready', function() {
        currentSkin = wp.customize('fbl_skin')();

        // When fbl_skin_picker changes, sync to fbl_skin and trigger skin change
        wp.customize('fbl_skin_picker', function(setting) {
            setting.bind(function(newSkin) {
                // Sync to the main fbl_skin setting so preview updates
                wp.customize('fbl_skin').set(newSkin);
            });
        });

        // When skin changes in preview, update controls
        wp.customize.previewer.bind('fbl-skin-changed', function(newSkin) {
            if (currentSkin !== newSkin) {
                currentSkin = newSkin;
                pendingSkin = newSkin;
                // If the section is already open, update immediately
                var section = wp.customize.section('fbl_theme_colors');
                if (section && section.expanded()) {
                    showSkinControls(newSkin);
                }
            }
        });

        // When Current Theme Colors section opens, show correct skin's controls
        wp.customize.section('fbl_theme_colors', function(section) {
            section.expanded.bind(function(isExpanded) {
                if (isExpanded) {
                    setTimeout(function() {
                        showSkinControls(currentSkin);
                        pendingSkin = null;
                    }, 150);
                }
            });
        });

        wp.customize.bind('saved', function() {
            var newSkin = wp.customize('fbl_skin')();
            if (currentSkin !== newSkin) {
                currentSkin = newSkin;
            }
        });
    });

})(jQuery);