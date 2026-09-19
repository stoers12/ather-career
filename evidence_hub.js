(function () {
    'use strict';
    var root = document.body;
    var sidebar = document.getElementById('evidence-hub-mobile-drawer');
    var backdrop = document.getElementById('evidence-hub-mobile-backdrop');
    var desktopToggle = document.getElementById('evidence-hub-sidebar-toggle');
    var mobileToggle = document.getElementById('evidence-hub-mobile-toggle');
    var mobileClose = document.getElementById('evidence-hub-mobile-close');
    var mobileQuery = window.matchMedia('(max-width: 799px)');
    var mobileReturnFocus = null;
    var preferenceKey = 'ather.evidenceHub.sidebarCollapsed';
    if (!root || !sidebar || !backdrop || !desktopToggle || !mobileToggle || !mobileClose) return;

    root.classList.add('evidence-hub-js');
    function setCollapsed(collapsed) {
        root.classList.toggle('evidence-hub-sidebar-collapsed', collapsed);
        desktopToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        try { window.localStorage.setItem(preferenceKey, collapsed ? 'true' : 'false'); } catch (error) { /* Expanded fallback remains usable. */ }
    }
    try { setCollapsed(window.localStorage.getItem(preferenceKey) === 'true'); } catch (error) { setCollapsed(false); }

    function setMobileDrawer(open, restoreFocus) {
        var isMobile = mobileQuery.matches;
        var isOpen = Boolean(open && isMobile);
        root.classList.toggle('evidence-hub-mobile-open', isOpen);
        root.classList.toggle('evidence-hub-mobile-scroll-lock', isOpen);
        mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        backdrop.hidden = !isOpen;
        backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        if (isMobile) {
            sidebar.inert = !isOpen;
            sidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        } else {
            sidebar.inert = false;
            sidebar.removeAttribute('aria-hidden');
        }
        if (isOpen) {
            mobileClose.focus();
            return;
        }
        if (restoreFocus && mobileQuery.matches && mobileReturnFocus && mobileReturnFocus.isConnected) {
            mobileReturnFocus.focus();
        }
        mobileReturnFocus = null;
    }

    desktopToggle.addEventListener('click', function () { setCollapsed(!root.classList.contains('evidence-hub-sidebar-collapsed')); });
    mobileToggle.addEventListener('click', function () { mobileReturnFocus = mobileToggle; setMobileDrawer(true, false); });
    mobileClose.addEventListener('click', function () { setMobileDrawer(false, true); });
    backdrop.addEventListener('click', function () { setMobileDrawer(false, true); });
    sidebar.addEventListener('click', function (event) { if (mobileQuery.matches && event.target.closest('a')) setMobileDrawer(false, false); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && root.classList.contains('evidence-hub-mobile-open')) setMobileDrawer(false, true); });
    mobileQuery.addEventListener('change', function () { setMobileDrawer(false, false); });
    window.addEventListener('pagehide', function () { setMobileDrawer(false, false); });
    setMobileDrawer(false, false);
}());
