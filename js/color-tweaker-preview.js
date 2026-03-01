(function($) {
    'use strict';

    console.log('Color Tweaker Preview Script Loading...');

    // Tokens that affect hover/active states and need explicit style injection
    // because CSS :hover pseudo-class doesn't reliably re-evaluate in the
    // Customizer preview iframe when CSS variables change.
    var hoverTokens = {
        '--fbl-header-bg': function(val) {
            return '#fbl-sticky-header-group { background-color: ' + val + ' !important; }';
        },
        '--fbl-bg-alt': function(val) {
            return '#fbl-members-of { background-color: ' + val + ' !important; }';
        },
        '--fbl-sidemenu-link-active': function(val) {
            return [
                '#fbl-left-menu .current-menu-item > a, #fbl-left-menu .current-menu-ancestor > a, #fbl-right-menu .current-menu-item > a, #fbl-right-menu .current-menu-ancestor > a { color: ' + val + ' !important; }',
            ].join('\n');
        },
        '--fbl-sidemenu-link-hover': function(val) {
            var restColor = getComputedStyle(document.body).getPropertyValue('--fbl-sidemenu-link').trim();
            return [
                '#fbl-left-menu ul.sub-menu > li > a, #fbl-right-menu ul.sub-menu > li > a { color: ' + restColor + ' !important; }',
                '#fbl-left-menu ul.sub-menu > li > a:hover, #fbl-right-menu ul.sub-menu > li > a:hover { color: ' + val + ' !important; }',
            ].join('\n');
        },
        '--fbl-header-bg': function(val) {
            return '#fbl-sticky-header-group { background-color: ' + val + ' !important; }';
        },
        '--fbl-bg-alt': function(val) {
            return '#fbl-members-of { background-color: ' + val + ' !important; }';
        },
        '--fbl-sidemenu-link-active': function(val) {
            return [
                '#fbl-left-menu ul.et-menu > li.current-menu-item > a, #fbl-left-menu ul.et-menu > li.current-menu-ancestor > a { color: ' + val + ' !important; font-weight: bold; }',
                '#fbl-right-menu ul.et-menu > li.current-menu-item > a, #fbl-right-menu ul.et-menu > li.current-menu-ancestor > a { color: ' + val + ' !important; font-weight: bold; }',
                '#fbl-left-menu ul.sub-menu > li.current-menu-item > a, #fbl-right-menu ul.sub-menu > li.current-menu-item > a { color: ' + val + ' !important; font-weight: bold; }',
                '.fbl-menu-footer .et-menu > li.current-menu-item > a, .fbl-menu-footer .et-menu > li.current-menu-ancestor > a { color: ' + val + ' !important; font-weight: bold; }',
            ].join('\n');
        },
        '--fbl-topmenu-hover': function(val) {
            var restColor = getComputedStyle(document.body).getPropertyValue('--fbl-sidemenu-link').trim();
            return [
                '#fbl-left-menu ul.et-menu > li > a, #fbl-right-menu ul.et-menu > li > a { color: ' + restColor + ' !important; }',
                '#fbl-left-menu ul.et-menu > li > a:hover, #fbl-right-menu ul.et-menu > li > a:hover { color: ' + val + ' !important; }',
            ].join('\n');
        },
        '--fbl-footer-link-hover': function(val) {
            return [
                'body .fbl-footer-grid a:hover { color: ' + val + ' !important; }',
                'body .fbl-footer .fbl-menu-footer .et-menu a:hover { color: ' + val + ' !important; }',
                'body .fbl-menu-column a:hover { color: ' + val + ' !important; }',
            ].join('\n');
        },
    };

    // Inject or update a named style block, always appended last to win
    function injectStyle(id, css) {
        var el = document.getElementById(id);
        if (!el) {
            el = document.createElement('style');
            el.id = id;
        }
        el.textContent = css;
        document.head.appendChild(el);
    }

    $(document).ready(function() {
        if (typeof wp !== 'undefined' && wp.customize) {
            console.log('Color Tweaker: Binding to settings...');

            $.each(wp.customize.settings.values, function(settingId, settingValue) {

                if (settingId.indexOf('fbl_global_') === 0 || settingId.indexOf('fbl_theme_') === 0) {

                    wp.customize(settingId, function(setting) {
                        setting.bind(function(newValue) {
                            var varName = settingId;

                            if (varName.indexOf('fbl_global_') === 0) {
                                varName = varName.replace('fbl_global_', '--fbl-').replace(/_/g, '-');
                                console.log('Color change (global):', varName, '=', newValue);
                                document.documentElement.style.setProperty(varName, newValue);

                            } else if (varName.indexOf('fbl_theme_') === 0) {
                                varName = varName.replace(/^fbl_theme_fbl_skin_[a-z]+_/, '--fbl-').replace(/_/g, '-');
                                console.log('Color change (skin):', varName, '=', newValue);
                                document.body.style.setProperty(varName, newValue);
                            }

                            // If this token affects hover states, inject explicit rules
                            if (hoverTokens[varName]) {
                                injectStyle(
                                    'fbl-hover-override-' + varName.replace(/[^a-z0-9]/g, '-'),
                                    hoverTokens[varName](newValue)
                                );
                                console.log('Hover override injected for:', varName);
                            }
                        });
                    });
                }
            });

            console.log('Color Tweaker: Bindings complete');
        }
    });

})(jQuery);