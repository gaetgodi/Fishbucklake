document.addEventListener("DOMContentLoaded", function () {

    function setupToggle(label, headingId, menuId) {
        const heading = document.getElementById(headingId);
        const menu    = document.getElementById(menuId);

        console.log("[FBL] setupToggle:", label, {
            headingFound: !!heading,
            menuFound: !!menu,
            headingId,
            menuId
        });

        if (!heading || !menu) {
            // If either is missing, do not attach a handler
            return;
        }

        // Make heading obviously clickable (mobile + desktop for testing)
        heading.style.cursor = "pointer";

        heading.addEventListener("click", function () {
            const isOpen = menu.classList.toggle("fbl-menu-open");
            console.log("[FBL] click:", label, "→ fbl-menu-open =", isOpen);
        });
    }

    // Always run; your CSS already limits behavior to mobile with media queries
    setupToggle("left",  "fbl-left-heading",  "fbl-left-menu");
    setupToggle("right", "fbl-right-heading", "fbl-right-menu");
});
