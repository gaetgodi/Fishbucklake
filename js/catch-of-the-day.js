/**
 * Catch of the Day Modal
 * Displays daily feature image on homepage, once per day
 */

(function() {
    'use strict';
    
    // Check if we should show the modal
    function shouldShowModal() {
        const cookieName = 'catchshown';
        const cookies = document.cookie.split(';');
        const today = new Date().toISOString().split('T')[0];
        
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim();
            if (cookie.indexOf(cookieName + '=') === 0) {
                const cookieValue = cookie.substring(cookieName.length + 1);
                if (cookieValue === today) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    // Mark as shown - set cookie
    function markAsShown() {
        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(0, 0, 0, 0);
        
        document.cookie = 'catchshown=' + today + '; expires=' + tomorrow.toUTCString() + '; path=/';
    }
    
    // Get current day of month
    function getCurrentDay() {
        const now = new Date();
        return now.getDate();
    }
    
    // Create and show modal
    function showModal() {
        const day = getCurrentDay();
        const imageUrl = '/wp-content/uploads/feature-images/day-' + 
                        (day < 10 ? '0' + day : day) + '.jpg';
        
        // Create modal HTML
        const modal = document.createElement('div');
        modal.id = 'fbl-catch-modal';
        modal.className = 'fbl-catch-modal';
        modal.innerHTML = `
            <div class="fbl-catch-overlay"></div>
            <div class="fbl-catch-content">
                <button class="fbl-catch-close" aria-label="Close">&times;</button>
                <h2 class="fbl-catch-title">Catch of the Day</h2>
                <div class="fbl-catch-image-container">
                    <img src="${imageUrl}" alt="Catch of the Day" class="fbl-catch-image">
                </div>
                <p class="fbl-catch-link">
                    <a href="/catch-gallery/">View All Daily Catches</a>
                </p>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Fade in after short delay
        setTimeout(function() {
            modal.classList.add('fbl-catch-visible');
        }, 100);
        
        // Close handlers
        const closeBtn = modal.querySelector('.fbl-catch-close');
        const overlay = modal.querySelector('.fbl-catch-overlay');
        const galleryLink = modal.querySelector('.fbl-catch-link a');
        
        function closeModal() {
            markAsShown();
            modal.classList.remove('fbl-catch-visible');
            setTimeout(function() {
                modal.remove();
            }, 300);
        }
        
        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);
        
        // Handle gallery link
        galleryLink.addEventListener('click', function(e) {
            e.preventDefault();
            markAsShown();
            modal.classList.remove('fbl-catch-visible');
            
            setTimeout(function() {
                modal.remove();
                window.location.href = galleryLink.href;
            }, 350);
        });
        
        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.parentNode) {
                closeModal();
            }
        });
        
        // Handle image load error
        const img = modal.querySelector('.fbl-catch-image');
        img.addEventListener('error', function() {
            closeModal();
        });
    }
    
    // Only show on homepage
    function init() {
        if (window.location.pathname === '/' || window.location.pathname === '') {
            if (shouldShowModal()) {
                showModal();
            }
        }
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();