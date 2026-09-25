(function () {
    'use strict';
    var root = document.body;
    var sidebar = document.getElementById('evidence-hub-mobile-drawer');
    var backdrop = document.getElementById('evidence-hub-mobile-backdrop');
    var desktopToggle = document.getElementById('evidence-hub-sidebar-toggle');
    var mobileToggle = document.getElementById('evidence-hub-mobile-toggle');
    var mobileClose = document.getElementById('evidence-hub-mobile-close');
    var mainContent = document.getElementById('main-content');
    var mobileQuery = window.matchMedia('(max-width: 1100px)');
    var sidebarKey = 'ather.evidenceHub.sidebarCollapsed';
    var mobileReturnFocus = null;
    if (!root || !sidebar || !backdrop || !desktopToggle || !mobileToggle || !mobileClose) return;

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
            setCollapsed(false, false);
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
        var always = trigger && trigger.getAttribute('data-sidebar-tooltip-always') === 'true';
        if (!trigger || (!always && !root.classList.contains('evidence-hub-sidebar-collapsed'))) return;
        tooltipTrigger = trigger;
        sidebarTooltip.textContent = trigger.getAttribute('data-sidebar-tooltip');
        sidebarTooltip.hidden = false;
        var bounds = trigger.getBoundingClientRect();
        var preferredLeft = sidebar.getBoundingClientRect().right + 8;
        var mobileLeft = Math.min(preferredLeft, window.innerWidth - sidebarTooltip.offsetWidth - 8);
        sidebarTooltip.style.left = Math.max(8, (isMobile() ? mobileLeft : preferredLeft)) + 'px';
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
    sidebar.addEventListener('click', function (event) {
        var disabled = event.target.closest('[aria-disabled="true"]');
        if (disabled) event.preventDefault();
    });
    sidebar.addEventListener('keydown', function (event) {
        if (event.target.closest('[aria-disabled="true"]') && (event.key === 'Enter' || event.key === ' ')) event.preventDefault();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hideSidebarTooltip();
        if (event.key !== 'Tab' || !isMobile() || !root.classList.contains('evidence-hub-mobile-open')) return;
        var controls = Array.from(sidebar.querySelectorAll('a, button, [tabindex="0"]')).filter(isVisible);
        var first = controls[0];
        var last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    root.classList.add('evidence-hub-js');
    setCollapsed(safeGet(sidebarKey) === 'true', false);
    applyViewportState();
    desktopToggle.addEventListener('click', function () { if (!isMobile()) setCollapsed(!root.classList.contains('evidence-hub-sidebar-collapsed'), true); });
    mobileToggle.addEventListener('click', function () { mobileReturnFocus = mobileToggle; setMobileDrawer(true, false); });
    mobileClose.addEventListener('click', function () { setMobileDrawer(false, true); });
    backdrop.addEventListener('click', function () { setMobileDrawer(false, true); });
    sidebar.addEventListener('click', function (event) { if (isMobile() && event.target.closest('a')) setMobileDrawer(false, false); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && isMobile() && root.classList.contains('evidence-hub-mobile-open')) setMobileDrawer(false, true); });
    mobileQuery.addEventListener('change', applyViewportState);
    window.addEventListener('pagehide', function () { setMobileDrawer(false, false); });
}());

(function () {
    'use strict';
    var page = document.querySelector('.evidence-hub-page-body');
    if (!page) return;

    var nav = document.querySelector('.evidence-hub-section-nav');
    if (!nav) return;
    var navLinks = Array.from(nav.querySelectorAll('a'));
    var sections = navLinks.map(function (link) { return document.getElementById(link.hash.slice(1)); }).filter(Boolean);
    var pinnedSection = null;
    var pinnedScrollY = null;
    function hashSection() { return sections.find(function (section) { return '#' + section.id === window.location.hash; }) || null; }
    function keepLinkVisible(link) {
        if (nav.scrollWidth <= nav.clientWidth) return;
        var navBounds = nav.getBoundingClientRect();
        var linkBounds = link.getBoundingClientRect();
        if (linkBounds.left < navBounds.left + 8) nav.scrollLeft += linkBounds.left - navBounds.left - 8;
        else if (linkBounds.right > navBounds.right - 8) nav.scrollLeft += linkBounds.right - navBounds.right + 8;
    }
    function updateCurrentSection() {
        if (pinnedSection && pinnedScrollY !== null && Math.abs(window.scrollY - pinnedScrollY) > 8) pinnedSection = null;
        var current = sections[0];
        var boundary = Math.max(document.querySelector('.evidence-hub-topbar').getBoundingClientRect().bottom + 40, window.innerHeight * .35);
        sections.forEach(function (section) { if (section.getBoundingClientRect().top <= boundary) current = section; });
        if (window.scrollY > 0 && window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2) current = sections[sections.length - 1];
        if (pinnedSection) current = pinnedSection;
        navLinks.forEach(function (link) {
            if (current && link.hash === '#' + current.id) {
                if (link.getAttribute('aria-current') !== 'true') keepLinkVisible(link);
                link.setAttribute('aria-current', 'true');
            }
            else link.removeAttribute('aria-current');
        });
    }
    navLinks.forEach(function (link) { link.addEventListener('focus', function () { keepLinkVisible(link); }); });
    window.addEventListener('scroll', updateCurrentSection, { passive: true });
    window.addEventListener('resize', updateCurrentSection);
    function updateForFragment() {
        pinnedSection = hashSection();
        pinnedScrollY = null;
        updateCurrentSection();
        window.setTimeout(function () { pinnedScrollY = window.scrollY; updateCurrentSection(); }, 500);
    }
    window.addEventListener('hashchange', updateForFragment);
    window.addEventListener('popstate', updateForFragment);
    if (hashSection()) updateForFragment();
    updateCurrentSection();

    var optionMenus = Array.from(page.querySelectorAll('.evidence-hub-options'));
    document.addEventListener('click', function (event) {
        optionMenus.forEach(function (menu) { if (menu.open && !menu.contains(event.target)) menu.open = false; });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        optionMenus.forEach(function (menu) {
            if (!menu.open) return;
            var focusWasInside = menu.contains(document.activeElement);
            menu.open = false;
            if (focusWasInside) menu.querySelector('summary').focus();
        });
    });

    var cards = Array.from(page.querySelectorAll('.evidence-hub-project-card'));
    var filters = Array.from(page.querySelectorAll('[data-evidence-filter]'));
    var more = page.querySelector('.evidence-hub-show-more');
    var selected = 'all';
    var expanded = false;
    var revealTarget = true;
    function targetCard() {
        var hash = window.location.hash;
        return /^#project-[1-9][0-9]*$/.test(hash) ? document.getElementById(hash.slice(1)) : null;
    }
    function renderProjects() {
        var target = targetCard();
        if (revealTarget && target && cards.includes(target)) { selected = 'all'; expanded = true; }
        filters.forEach(function (button) { button.setAttribute('aria-pressed', button.dataset.evidenceFilter === selected ? 'true' : 'false'); });
        var matches = cards.filter(function (card) { return selected === 'all' || card.dataset.evidenceProjectStatus === selected; });
        matches.forEach(function (card, index) { card.hidden = !expanded && index >= 6; });
        cards.forEach(function (card) { if (!matches.includes(card)) card.hidden = true; });
        if (more) more.hidden = expanded || matches.length <= 6;
    }
    filters.forEach(function (button) {
        button.addEventListener('click', function () { revealTarget = false; selected = button.dataset.evidenceFilter; expanded = false; renderProjects(); });
    });
    if (more) more.addEventListener('click', function () { expanded = true; renderProjects(); });
    window.addEventListener('hashchange', function () { revealTarget = true; renderProjects(); });
    renderProjects();
}());
