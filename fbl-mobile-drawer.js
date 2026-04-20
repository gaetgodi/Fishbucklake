document.addEventListener("DOMContentLoaded", function () {

    const body     = document.body;
    const html     = document.documentElement;
    const overlay  = document.getElementById("fbl-mm-overlay");
    const openBtn  = document.getElementById("fbl-mm-open");
    const closeBtn = document.getElementById("fbl-mm-close");

    if (!overlay) return;

    function resetSections() {
        document.querySelectorAll('.fbl-mm-toggle').forEach(function(heading) {
            const list  = heading.nextElementSibling;
            const arrow = heading.querySelector('.fbl-mm-arrow');
            if (heading.classList.contains('fbl-mm-open')) {
                // Our Facilities stays open, everything else closes
                if (!heading.textContent.includes('Our Facilities')) {
                    list.classList.add('fbl-mm-collapsed');
                    arrow.textContent = '▶';
                    heading.classList.remove('fbl-mm-open');
                }
            } else {
                list.classList.add('fbl-mm-collapsed');
                arrow.textContent = '▶';
            }
        });
    }

    function closeAll() {
        body.classList.remove("fbl-mm-open");
        html.classList.remove("fbl-mm-open");
        resetSections();
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

    // Section toggles
    document.querySelectorAll('.fbl-mm-toggle').forEach(function(heading) {
        heading.addEventListener('click', function() {
            const list  = this.nextElementSibling;
            const arrow = this.querySelector('.fbl-mm-arrow');
            const isOpen = !list.classList.contains('fbl-mm-collapsed');
            if (isOpen) {
                list.classList.add('fbl-mm-collapsed');
                arrow.textContent = '▶';
                this.classList.remove('fbl-mm-open');
            } else {
                list.classList.remove('fbl-mm-collapsed');
                arrow.textContent = '▼';
                this.classList.add('fbl-mm-open');
            }
        });
    });
});