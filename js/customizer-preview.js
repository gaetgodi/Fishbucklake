(function($) {
    'use strict';
    
    console.log('FBL Skin Preview Script Loading...');
    
    $(document).ready(function() {
        console.log('DOM Ready');
        
        var checkCustomizer = setInterval(function() {
            if (typeof wp !== 'undefined' && wp.customize) {
                clearInterval(checkCustomizer);
                console.log('wp.customize is ready!');
                
                // Use the correct API for postMessage transport
                wp.customize('fbl_skin', function(value) {
                    console.log('Bound to fbl_skin setting');
                    
                    value.bind(function(newSkin) {
                        console.log('=== SKIN CHANGE RECEIVED ===');
                        console.log('New skin:', newSkin);
                        console.log('Body classes BEFORE:', document.body.className);
                        
                        // Remove old skin classes
                        document.body.className = document.body.className.replace(/fbl-skin-\S+/g, '').trim();
                        
                        // Add new skin
                        document.body.classList.add(newSkin);
                        
                        console.log('Body classes AFTER:', document.body.className);
                        
                        // Send message to parent Customizer to reload controls
                        wp.customize.preview.send('fbl-skin-changed', newSkin);
                    });
                });
            }
        }, 100);
        
        setTimeout(function() { clearInterval(checkCustomizer); }, 10000);
    });
    
})(jQuery);