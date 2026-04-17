document.addEventListener("DOMContentLoaded", function () {

    const body    = document.body;
    const html    = document.documentElement;
    const overlay = document.getElementById("fbl-mm-overlay");
    const openBtn = document.getElementById("fbl-mm-open");
    const closeBtn = document.getElementById("fbl-mm-close");

    if (!overlay) return;

    function closeAll() {
        body.classList.remove("fbl-mm-open");
        html.classList.remove("fbl-mm-open");
    }

    function toggleDrawer() {
        if (body.classList.contains("fbl-mm-open")) {
            closeAll();
        } else {
            body.classList.add("fbl-mm-open");
            html.classList.add("fbl-mm-open");
        }
    }

    openBtn?.addEventListener("click", toggleDrawer);
    closeBtn?.addEventListener("click", closeAll);
    overlay.addEventListener("click", closeAll);

    document.addEventListener("click", function(e) {
        if (!body.classList.contains("fbl-mm-open")) return;
        const el = e.target;
        if (!el.closest('#fbl-mm-bottom-sheet') && 
            !el.closest('#fbl-mm-open') && 
            el.id !== 'fbl-mm-overlay') {
            closeAll();
        }
    });

    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") closeAll();
    });
});