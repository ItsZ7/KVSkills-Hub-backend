console.log("KVSkills Hub Loaded");

document.addEventListener("DOMContentLoaded", function () {

    // =========================================================================
    // SIDEBAR TOGGLE (dashboard — mobile)
    // =========================================================================
    const sidebar = document.querySelector(".sidebar");
    const toggleBtn = document.querySelector(".sidebar-toggle");
    const overlaySidebar = document.querySelector(".sidebar-overlay");
    const closeBtn = document.querySelector(".sidebar-close-btn");
    const reopenBtn = document.querySelector(".sidebar-reopen-btn");

    if (sidebar && toggleBtn) {
        toggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("open");
            if (overlaySidebar) overlaySidebar.classList.toggle("active");
        });
    }

    if (overlaySidebar) {
        overlaySidebar.addEventListener("click", function () {
            sidebar.classList.remove("open");
            overlaySidebar.classList.remove("active");
        });
    }

    // Close button inside the sidebar - works on mobile (off-canvas) and desktop (collapse)
    if (sidebar && closeBtn) {
        closeBtn.addEventListener("click", function () {
            if (window.innerWidth >= 992) {
                sidebar.classList.add("collapsed");
                document.body.classList.add("sidebar-collapsed");
            } else {
                sidebar.classList.remove("open");
                if (overlaySidebar) overlaySidebar.classList.remove("active");
            }
        });
    }

    // Floating button to bring the sidebar back on desktop
    if (sidebar && reopenBtn) {
        reopenBtn.addEventListener("click", function () {
            sidebar.classList.remove("collapsed");
            document.body.classList.remove("sidebar-collapsed");
        });
    }

    // =========================================================================
    // PUBLIC NAVBAR — Hamburger menu (awam sahaja)
    // =========================================================================
    const publicNavbar    = document.getElementById("publicNavbar");
    const hamburger       = document.getElementById("publicNavHamburger");
    const navLinks        = document.getElementById("publicNavLinks");
    const navOverlay      = document.getElementById("publicNavOverlay");

    if (publicNavbar && hamburger && navLinks) {

        function openMobileNav() {
            navLinks.classList.add("is-open");
            hamburger.classList.add("is-open");
            hamburger.setAttribute("aria-expanded", "true");
            if (navOverlay) {
                navOverlay.classList.add("active");
                navOverlay.removeAttribute("aria-hidden");
            }
            document.body.style.overflow = "hidden";
        }

        function closeMobileNav() {
            navLinks.classList.remove("is-open");
            hamburger.classList.remove("is-open");
            hamburger.setAttribute("aria-expanded", "false");
            if (navOverlay) {
                navOverlay.classList.remove("active");
                navOverlay.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
        }

        hamburger.addEventListener("click", function () {
            const isOpen = navLinks.classList.contains("is-open");
            isOpen ? closeMobileNav() : openMobileNav();
        });

        if (navOverlay) {
            navOverlay.addEventListener("click", closeMobileNav);
        }

        // Close on Escape key
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && navLinks.classList.contains("is-open")) {
                closeMobileNav();
                hamburger.focus();
            }
        });

        // Close when a nav link is tapped (smooth UX on mobile)
        navLinks.querySelectorAll(".public-navbar-link").forEach(function (link) {
            link.addEventListener("click", function () {
                if (window.innerWidth < 992) {
                    closeMobileNav();
                }
            });
        });

        // Close drawer when resizing to desktop
        window.addEventListener("resize", function () {
            if (window.innerWidth >= 992) {
                closeMobileNav();
            }
        });
    }

    // =========================================================================
    // PUBLIC NAVBAR — Scroll-opacity (glassmorphism enhancement)
    // =========================================================================
    if (publicNavbar) {
        function handleNavbarScroll() {
            if (window.scrollY > 20) {
                publicNavbar.classList.add("scrolled");
            } else {
                publicNavbar.classList.remove("scrolled");
            }
        }
        // Passive listener for performance
        window.addEventListener("scroll", handleNavbarScroll, { passive: true });
        handleNavbarScroll(); // Run once on load
    }
});