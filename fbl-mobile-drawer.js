document.addEventListener("DOMContentLoaded", function () {

    const body    = document.body;
    const html    = document.documentElement;
    const overlay = document.getElementById("fbl-mm-overlay");

    const openMain   = document.getElementById("fbl-mm-open");
    const closeMain  = document.getElementById("fbl-mm-close");

    const openFooter  = document.getElementById("fbl-footer-mm-open");
    const closeFooter = document.getElementById("fbl-footer-mm-close");

    if (!overlay) return;

    function closeAll() {
        body.classList.remove("fbl-mm-open", "fbl-footer-mm-open");
        html.classList.remove("fbl-mm-open", "fbl-footer-mm-open");
    }

    function toggleMainDrawer() {
        // If main drawer is open, close it; otherwise open it
        if (body.classList.contains("fbl-mm-open")) {
            closeAll();
        } else {
            closeAll(); // Close any other open drawer first
            body.classList.add("fbl-mm-open");
            html.classList.add("fbl-mm-open");
        }
    }

    function toggleFooterDrawer() {
        // If footer drawer is open, close it; otherwise open it
        if (body.classList.contains("fbl-footer-mm-open")) {
            closeAll();
        } else {
            closeAll(); // Close any other open drawer first
            body.classList.add("fbl-footer-mm-open");
            html.classList.add("fbl-footer-mm-open");
        }
    }

    openMain?.addEventListener("click", toggleMainDrawer);
    closeMain?.addEventListener("click", closeAll);

    openFooter?.addEventListener("click", toggleFooterDrawer);
    closeFooter?.addEventListener("click", closeAll);

    overlay.addEventListener("click", closeAll);
    
    // Close drawer when clicking on page content area
    document.addEventListener("click", function(e) {
        // Only close if a drawer is open
        if (!body.classList.contains("fbl-mm-open") && !body.classList.contains("fbl-footer-mm-open")) {
            return;
        }
        
        // Don't close if clicking on drawer itself, buttons, or overlay
        const clickedElement = e.target;
        const isDrawer = clickedElement.closest('#fbl-mm-bottom-sheet, #fbl-footer-mm-bottom-sheet');
        const isButton = clickedElement.closest('#fbl-mm-drawer');
        const isOverlay = clickedElement.id === 'fbl-mm-overlay';
        
        // If clicked on page content (not drawer, not buttons), close drawer
        if (!isDrawer && !isButton && !isOverlay) {
            closeAll();
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeAll();
    });
});