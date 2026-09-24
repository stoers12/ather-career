(function () {
    'use strict';
    var root = document.body;
    var sidebar = document.getElementById('evidence-hub-mobile-drawer');
    var backdrop = document.getElementById('evidence-hub-mobile-backdrop');
    var desktopToggle = document.getElementById('evidence-hub-sidebar-toggle');
    var mobileToggle = document.getElementById('evidence-hub-mobile-toggle');
    var mobileClose = document.getElementById('evidence-hub-mobile-close');
    var themeToggle = document.getElementById('evidence-hub-theme-toggle');
    var mainContent = document.getElementById('main-content');
    var mobileQuery = window.matchMedia('(max-width: 1100px)');
    var sidebarKey = 'ather.evidenceHub.sidebarCollapsed';
    var themeKey = 'ather.evidenceHub.theme';
    var mobileReturnFocus = null;
    if (!root || !sidebar || !backdrop || !desktopToggle || !mobileToggle || !mobileClose || !themeToggle) return;

    function safeGet(key) { try { return window.localStorage.getItem(key); } catch (error) { return null; } }
    function safeSet(key, value) { try { window.localStorage.setItem(key, value); } catch (error) { /* Visual state remains available for this page. */ } }
    function isMobile() { return mobileQuery.matches; }
    function isVisible(element) { return Boolean(element && element.getClientRects().length); }
    function setPanelState(collapsed) {
        var closeIcon = desktopToggle.querySelector('[data-evidence-hub-panel-state="close"]');
        var openIcon = desktopToggle.querySelector('[data-evidence-hub-panel-state="open"]');
        var label = collapsed ? 'Expand navigation' : 'Collapse navigation';
        desktopToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        desktopToggle.setAttribute('aria-label', label);
        desktopToggle.setAttribute('data-sidebar-tooltip', label);
        if (closeIcon) closeIcon.hidden = collapsed;
        if (openIcon) openIcon.hidden = !collapsed;
    }
    function setCollapsed(collapsed, persist) {
        hideSidebarTooltip();
        var value = Boolean(collapsed);
        root.classList.toggle('evidence-hub-sidebar-collapsed', value);
        setPanelState(value);
        if (persist) safeSet(sidebarKey, value ? 'true' : 'false');
    }
    function setTheme(theme, persist) {
        var value = theme === 'dark' ? 'dark' : 'light';
        var dark = value === 'dark';
        root.setAttribute('data-evidence-hub-theme', value);
        themeToggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
        themeToggle.setAttribute('aria-label', dark ? 'Enable light theme' : 'Enable dark theme');
        themeToggle.setAttribute('data-sidebar-tooltip', dark ? 'Enable light theme' : 'Enable dark theme');
        if (persist) safeSet(themeKey, value);
    }
    function setMobileDrawer(open, restoreFocus) {
        hideSidebarTooltip();
        var active = Boolean(open && isMobile());
        root.classList.toggle('evidence-hub-mobile-open', active);
        root.classList.toggle('evidence-hub-mobile-scroll-lock', active);
        if (mainContent) {
            mainContent.inert = active;
            if (active) mainContent.setAttribute('aria-hidden', 'true'); else mainContent.removeAttribute('aria-hidden');
        }
        mobileToggle.setAttribute('aria-expanded', active ? 'true' : 'false');
        mobileToggle.inert = active;
        backdrop.hidden = !active;
        backdrop.setAttribute('aria-hidden', active ? 'false' : 'true');
        if (isMobile()) {
            sidebar.inert = !active;
            sidebar.setAttribute('aria-hidden', active ? 'false' : 'true');
        } else {
            sidebar.inert = false;
            sidebar.removeAttribute('aria-hidden');
        }
        if (active) { mobileClose.focus(); return; }
        if (restoreFocus && isMobile() && mobileReturnFocus && mobileReturnFocus.isConnected && isVisible(mobileReturnFocus)) mobileReturnFocus.focus();
        mobileReturnFocus = null;
    }
    function applyViewportState() {
        var focused = document.activeElement;
        if (isMobile()) {
            setMobileDrawer(false, false);
            if (!isVisible(focused) && isVisible(mobileToggle)) mobileToggle.focus();
            return;
        }
        setMobileDrawer(false, false);
        setCollapsed(safeGet(sidebarKey) === 'true', false);
        if (!isVisible(focused) && isVisible(desktopToggle)) desktopToggle.focus();
    }

    // A viewport-positioned tooltip lives outside the scrolling navigation region.
    var sidebarTooltip = document.createElement('div');
    sidebarTooltip.id = 'owner-sidebar-tooltip';
    sidebarTooltip.className = 'owner-sidebar-tooltip';
    sidebarTooltip.setAttribute('role', 'tooltip');
    sidebarTooltip.hidden = true;
    root.appendChild(sidebarTooltip);
    var tooltipTrigger = null;
    function hideSidebarTooltip() {
        if (!sidebarTooltip) return;
        sidebarTooltip.hidden = true;
        if (tooltipTrigger) tooltipTrigger.removeAttribute('aria-describedby');
        tooltipTrigger = null;
    }
    function showSidebarTooltip(trigger) {
        hideSidebarTooltip();
        if (!trigger || isMobile() || !root.classList.contains('evidence-hub-sidebar-collapsed')) return;
        tooltipTrigger = trigger;
        sidebarTooltip.textContent = trigger.getAttribute('data-sidebar-tooltip');
        sidebarTooltip.hidden = false;
        var bounds = trigger.getBoundingClientRect();
        sidebarTooltip.style.left = (sidebar.getBoundingClientRect().right + 8) + 'px';
        sidebarTooltip.style.top = Math.max(8, Math.min(window.innerHeight - sidebarTooltip.offsetHeight - 8, bounds.top + (bounds.height - sidebarTooltip.offsetHeight) / 2)) + 'px';
        trigger.setAttribute('aria-describedby', sidebarTooltip.id);
    }
    sidebar.querySelectorAll('[data-sidebar-tooltip]').forEach(function (trigger) {
        trigger.addEventListener('mouseenter', function () { showSidebarTooltip(trigger); });
        trigger.addEventListener('mouseleave', hideSidebarTooltip);
        trigger.addEventListener('focus', function () { showSidebarTooltip(trigger); });
        trigger.addEventListener('blur', hideSidebarTooltip);
    });
    sidebar.querySelector('nav').addEventListener('scroll', hideSidebarTooltip);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hideSidebarTooltip();
        if (event.key !== 'Tab' || !isMobile() || !root.classList.contains('evidence-hub-mobile-open')) return;
        var controls = Array.from(sidebar.querySelectorAll('a, button')).filter(isVisible);
        var first = controls[0];
        var last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    root.classList.add('evidence-hub-js');
    setTheme(safeGet(themeKey), false);
    setCollapsed(safeGet(sidebarKey) === 'true', false);
    applyViewportState();
    desktopToggle.addEventListener('click', function () { if (!isMobile()) setCollapsed(!root.classList.contains('evidence-hub-sidebar-collapsed'), true); });
    mobileToggle.addEventListener('click', function () { mobileReturnFocus = mobileToggle; setMobileDrawer(true, false); });
    mobileClose.addEventListener('click', function () { setMobileDrawer(false, true); });
    backdrop.addEventListener('click', function () { setMobileDrawer(false, true); });
    sidebar.addEventListener('click', function (event) { if (isMobile() && event.target.closest('a')) setMobileDrawer(false, false); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && isMobile() && root.classList.contains('evidence-hub-mobile-open')) setMobileDrawer(false, true); });
    themeToggle.addEventListener('click', function () { setTheme(root.getAttribute('data-evidence-hub-theme') === 'dark' ? 'light' : 'dark', true); });
    mobileQuery.addEventListener('change', applyViewportState);
    window.addEventListener('pagehide', function () { setMobileDrawer(false, false); });
}());
