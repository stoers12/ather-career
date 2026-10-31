(function () {
    'use strict';
    try {
        if (localStorage.getItem('ather.owner.textSize') === 'large') document.body.setAttribute('data-owner-text-size', 'large');
        if (localStorage.getItem('ather.owner.motion') === 'reduce') document.body.setAttribute('data-owner-motion', 'reduce');
    } catch (error) { /* Default preferences remain available. */ }
    document.querySelectorAll('.evidence-hub-themes [data-owner-theme]').forEach(function (button) { button.hidden = false; });
    var nav = document.querySelector('[data-evidence-layers]');
    if (!nav) return;
    var tabs = Array.from(nav.querySelectorAll('[data-evidence-layer]'));
    var panels = Array.from(document.querySelectorAll('[data-evidence-layer-panel]'));
    nav.setAttribute('role', 'tablist');
    tabs.forEach(function (tab) { tab.setAttribute('role', 'tab'); tab.setAttribute('aria-controls', tab.dataset.evidenceLayer); });
    // Panels hold focusable controls, so they stay out of the tab order (APG); no focus frame on a whole layer.
    panels.forEach(function (panel) { panel.setAttribute('role', 'tabpanel'); });
    function select(id, focus) {
        tabs.forEach(function (tab) {
            var selected = tab.dataset.evidenceLayer === id;
            tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1;
            if (focus && selected) tab.focus();
        });
        panels.forEach(function (panel) { panel.hidden = panel.id !== id; });
        // A newly visible track must recompute its widths, without a request.
        window.dispatchEvent(new Event('resize'));
    }
    function fromFragment() {
        var id = window.location.hash.slice(1);
        var target = document.getElementById(id);
        var panel = target && (target.matches('[data-evidence-layer-panel]') ? target : target.closest('[data-evidence-layer-panel]'));
        select(panel ? panel.id : 'records', false);
    }
    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function (event) {
            event.preventDefault(); window.history.replaceState(null, '', tab.hash); select(tab.dataset.evidenceLayer, false);
        });
        tab.addEventListener('keydown', function (event) {
            var rtl = document.documentElement.dir === 'rtl';
            var delta = event.key === 'ArrowRight' ? (rtl ? -1 : 1) : (event.key === 'ArrowLeft' ? (rtl ? 1 : -1) : 0);
            var next = event.key === 'Home' ? 0 : (event.key === 'End' ? tabs.length - 1 : (index + delta + tabs.length) % tabs.length);
            if (!delta && event.key !== 'Home' && event.key !== 'End') return;
            event.preventDefault(); select(tabs[next].dataset.evidenceLayer, true);
            window.history.replaceState(null, '', tabs[next].hash);
        });
    });
    window.addEventListener('hashchange', fromFragment);
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href^="#"]');
        var target = link && document.getElementById(link.hash.slice(1));
        var panel = target && target.closest('[data-evidence-layer-panel]');
        if (panel) select(panel.id, false);
    });
    fromFragment();
    var error = document.querySelector('#insights [aria-invalid="true"], #insights [role="alert"]');
    if (error) select('insights', false);
}());

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
    var accountMenu = document.querySelector('.evidence-hub-account-menu');
    var accountTrigger = accountMenu && accountMenu.querySelector('summary');
    var accountPanel = accountMenu && accountMenu.querySelector('.evidence-hub-account-panel');
    if (!root || !sidebar || !backdrop || !desktopToggle || !mobileToggle || !mobileClose) return;

    function safeGet(key) { try { return window.localStorage.getItem(key); } catch (error) { return null; } }
    function safeSet(key, value) { try { window.localStorage.setItem(key, value); } catch (error) { /* Visual state remains available for this page. */ } }
    function isMobile() { return mobileQuery.matches; }
    function isVisible(element) { return Boolean(element && element.getClientRects().length); }
    function closeAccountMenu(restoreFocus) {
        if (!accountMenu || !accountMenu.open) return;
        accountMenu.open = false;
        if (restoreFocus && isVisible(accountTrigger)) accountTrigger.focus();
    }
    function positionAccountMenu() {
        if (!accountMenu || !accountMenu.open) return;
        var bounds = accountTrigger.getBoundingClientRect();
        accountPanel.style.left = '0px';
        accountPanel.style.top = '0px';
        // The mobile drawer is transformed; compensate its fixed-position origin.
        var origin = accountPanel.getBoundingClientRect();
        var rtl = document.documentElement.dir === 'rtl';
        var rail = sidebar.getBoundingClientRect();
        var left = !isMobile() && root.classList.contains('evidence-hub-sidebar-collapsed')
            ? (rtl ? rail.left - accountPanel.offsetWidth - 8 : rail.right + 8)
            : (rtl ? bounds.right - accountPanel.offsetWidth : bounds.left);
        var top = bounds.top - accountPanel.offsetHeight - 8;
        if (top < 8) top = bounds.bottom + 8;
        accountPanel.style.left = Math.max(8, Math.min(left, window.innerWidth - accountPanel.offsetWidth - 8)) - origin.left + 'px';
        accountPanel.style.top = Math.max(8, Math.min(top, window.innerHeight - accountPanel.offsetHeight - 8)) - origin.top + 'px';
    }
    function setPanelState(collapsed) {
        var closeIcon = desktopToggle.querySelector('[data-evidence-hub-panel-state="close"]');
        var openIcon = desktopToggle.querySelector('[data-evidence-hub-panel-state="open"]');
        var label = collapsed
            ? (desktopToggle.getAttribute('data-label-expand') || 'Expand navigation')
            : (desktopToggle.getAttribute('data-label-collapse') || 'Collapse navigation');
        desktopToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        desktopToggle.setAttribute('aria-label', label);
        desktopToggle.setAttribute('data-sidebar-tooltip', label);
        if (closeIcon) closeIcon.hidden = collapsed;
        if (openIcon) openIcon.hidden = !collapsed;
    }
    function setCollapsed(collapsed, persist) {
        hideSidebarTooltip();
        closeAccountMenu(false);
        var value = Boolean(collapsed);
        root.classList.toggle('evidence-hub-sidebar-collapsed', value);
        setPanelState(value);
        if (persist) safeSet(sidebarKey, value ? 'true' : 'false');
    }
    function setMobileDrawer(open, restoreFocus) {
        hideSidebarTooltip();
        closeAccountMenu(false);
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
        if (accountMenu && accountMenu.open && accountMenu.contains(trigger)) return;
        var always = trigger && trigger.getAttribute('data-sidebar-tooltip-always') === 'true';
        if (!trigger || (!always && !root.classList.contains('evidence-hub-sidebar-collapsed'))) return;
        tooltipTrigger = trigger;
        sidebarTooltip.textContent = trigger.getAttribute('data-sidebar-tooltip');
        sidebarTooltip.hidden = false;
        var bounds = trigger.getBoundingClientRect();
        var rail = sidebar.getBoundingClientRect();
        var preferredLeft = document.documentElement.dir === 'rtl'
            ? rail.left - sidebarTooltip.offsetWidth - 8 : rail.right + 8;
        sidebarTooltip.style.left = Math.max(8, Math.min(preferredLeft, window.innerWidth - sidebarTooltip.offsetWidth - 8)) + 'px';
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
        if (event.key === 'Escape' && accountMenu && accountMenu.open) {
            event.preventDefault();
            event.stopImmediatePropagation();
            hideSidebarTooltip();
            closeAccountMenu(true);
            return;
        }
        if (event.key === 'Escape') hideSidebarTooltip();
        if (event.key !== 'Tab' || !isMobile() || !root.classList.contains('evidence-hub-mobile-open')) return;
        var controls = Array.from(sidebar.querySelectorAll('a, button, summary, [tabindex="0"]')).filter(function (element) {
            return isVisible(element) && (!accountPanel || !accountPanel.contains(element) || accountMenu.open);
        });
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
    if (accountMenu) {
        accountMenu.addEventListener('toggle', function () {
            if (accountMenu.open) { hideSidebarTooltip(); positionAccountMenu(); }
        });
        accountMenu.addEventListener('keydown', function (event) {
            if (event.key === 'Tab') window.setTimeout(function () {
                if (!accountMenu.contains(document.activeElement)) closeAccountMenu(false);
            }, 0);
        });
        document.addEventListener('click', function (event) {
            if (!accountMenu.contains(event.target)) closeAccountMenu(true);
        });
        window.addEventListener('resize', positionAccountMenu);
        window.addEventListener('scroll', function (event) {
            if (!accountPanel.contains(event.target)) positionAccountMenu();
        }, true);
    }
    window.addEventListener('pagehide', function () { setMobileDrawer(false, false); });
}());

(function () {
    'use strict';
    var page = document.querySelector('.evidence-hub-page-body');
    if (!page) return;

    var nav = document.querySelector('.evidence-hub-section-nav');
    if (!nav) return;
    // Only section targets use the measured chrome offset; fixed drawer focus must not scroll the page.
    var topbar = document.querySelector('.evidence-hub-topbar');
    var sheet = Array.from(document.styleSheets).find(function (item) { return item.href && new URL(item.href).pathname.endsWith('/assets/css/evidence_hub.css'); });
    var rootRule = sheet && Array.from(sheet.cssRules).find(function (rule) { return rule.selectorText === 'body.evidence-hub-page'; });
    function updateNavigationOffset() {
        if (rootRule) rootRule.style.setProperty('--eh-navigation-offset', Math.ceil(topbar.getBoundingClientRect().height) + 16 + 'px');
    }
    updateNavigationOffset();
    if (typeof window.ResizeObserver === 'function') new ResizeObserver(updateNavigationOffset).observe(topbar);
    else window.addEventListener('resize', updateNavigationOffset);
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
        sections.forEach(function (section) { if (section.getClientRects().length && section.getBoundingClientRect().top <= boundary) current = section; });
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

    var activeOptionMenu = null;
    var activeOptionTrigger = null;
    var activeProjectId = null;
    function optionTrigger(menu) {
        if (!menu.classList.contains('evidence-hub-project-options')) return menu.querySelector(':scope > summary');
        var id = menu.dataset.projectId;
        if (!/^[1-9][0-9]*$/.test(id || '')) return null;
        var card = document.getElementById('project-' + id);
        var trigger = card && card.querySelector('.evidence-hub-project-options > summary');
        var panel = card && card.querySelector('.evidence-hub-project-options > div');
        return trigger && panel && trigger.id === 'project-' + id + '-options-trigger'
            && trigger.getAttribute('aria-controls') === panel.id ? trigger : null;
    }
    function closeOptionMenu(menu) {
        if (!menu) return;
        menu.open = false;
        var trigger = optionTrigger(menu);
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        if (activeOptionMenu === menu) {
            activeOptionMenu = null;
            activeOptionTrigger = null;
            activeProjectId = null;
        }
    }
    function closeProjectMenus() {
        page.querySelectorAll('.evidence-hub-project-options[open]').forEach(closeOptionMenu);
    }
    function positionProjectMenu(menu) {
        if (!menu.classList.contains('evidence-hub-project-options') || !menu.open) return;
        var trigger = optionTrigger(menu);
        var panel = menu.querySelector(':scope > div');
        if (!trigger || !panel) return;
        var bounds = trigger.getBoundingClientRect();
        panel.style.top = Math.min(bounds.bottom + 4, window.innerHeight - panel.offsetHeight - 8) + 'px';
        panel.style.left = Math.max(8, Math.min(bounds.right - panel.offsetWidth, window.innerWidth - panel.offsetWidth - 8)) + 'px';
    }
    page.addEventListener('toggle', function (event) {
        var menu = event.target;
        if (!menu.matches || !menu.matches('.evidence-hub-options')) return;
        var trigger = optionTrigger(menu);
        if (trigger) trigger.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
        if (!menu.open) return;
        page.querySelectorAll('.evidence-hub-options[open]').forEach(function (other) {
            if (other !== menu) closeOptionMenu(other);
        });
        activeOptionMenu = menu;
        activeOptionTrigger = trigger;
        activeProjectId = menu.classList.contains('evidence-hub-project-options') ? menu.dataset.projectId : null;
        if (track) lastTrackScroll = track.scrollLeft;
        positionProjectMenu(menu);
    }, true);
    page.addEventListener('click', function (event) {
        var menu = event.target.closest('.evidence-hub-options');
        if (menu && event.target.closest('a, button')) closeOptionMenu(menu);
    });
    document.addEventListener('click', function (event) {
        page.querySelectorAll('.evidence-hub-options[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) closeOptionMenu(menu);
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var menu = activeOptionMenu && activeOptionMenu.isConnected && activeOptionMenu.open
            ? activeOptionMenu : page.querySelector('.evidence-hub-options[open]');
        if (!menu) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        var trigger = activeOptionMenu === menu && activeOptionTrigger && activeOptionTrigger.isConnected
            ? activeOptionTrigger : optionTrigger(menu);
        var projectId = menu.classList.contains('evidence-hub-project-options') ? menu.dataset.projectId : null;
        if (projectId && (projectId !== activeProjectId || trigger !== optionTrigger(menu))) trigger = optionTrigger(menu);
        closeOptionMenu(menu);
        if (trigger && trigger.isConnected && trigger.getClientRects().length > 0
            && !trigger.closest('[hidden], [inert], [aria-disabled="true"]')
            && !trigger.matches(':disabled')) trigger.focus({ preventScroll: true });
    }, true);

    window.addEventListener('resize', function () { page.querySelectorAll('.evidence-hub-options[open]').forEach(positionProjectMenu); });

    var cards = Array.from(page.querySelectorAll('.evidence-hub-project-card'));
    var filters = Array.from(page.querySelectorAll('[data-evidence-filter]'));
    var carousel = page.querySelector('[data-evidence-carousel]');
    var track = page.querySelector('[data-evidence-carousel-track]');
    var lastTrackScroll = track ? track.scrollLeft : 0;
    var navigation = page.querySelector('[data-evidence-carousel-navigation]');
    var previous = page.querySelector('[data-evidence-carousel-previous]');
    var next = page.querySelector('[data-evidence-carousel-next]');
    var counter = page.querySelector('[data-evidence-carousel-counter]');
    var filterEmpty = page.querySelector('[data-evidence-filter-empty]');
    var review = page.querySelector('[data-review-project-evidence]');
    var mobileCards = window.matchMedia('(max-width: 700px)');
    var tabletCards = window.matchMedia('(max-width: 1100px)');
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var selected = 'all';
    var position = 0;
    var matches = [];
    if (carousel) carousel.classList.add('is-enhanced');
    var filterGroup = page.querySelector('.evidence-hub-project-filters');
    if (filterGroup) filterGroup.hidden = false;
    function counterLabel(attribute, fallback, values) {
        var template = (carousel && carousel.getAttribute(attribute)) || fallback;
        return Object.keys(values).reduce(function (text, key) { return text.split(key).join(String(values[key])); }, template);
    }
    function visibleCount() { return mobileCards.matches ? 1 : (tabletCards.matches ? 2 : 3); }
    function maxPosition() { return Math.max(0, matches.length - visibleCount()); }
    function scrollToPosition(animate) {
        if (!track || !matches[position]) return;
        var left = matches[position].offsetLeft - matches[0].offsetLeft;
        track.scrollTo({ left: left, behavior: animate && !reducedMotion.matches ? 'smooth' : 'instant' });
    }
    function updateNavigation() {
        var size = visibleCount();
        var total = matches.length;
        position = Math.min(position, maxPosition());
        if (navigation) navigation.hidden = total <= size;
        if (previous) previous.disabled = position === 0;
        if (next) next.disabled = position >= maxPosition();
        if (counter) counter.textContent = total === 0 ? '' : (size === 1
            ? counterLabel('data-label-single', 'Project {position} of {total}', { '{position}': position + 1, '{total}': total })
            : counterLabel('data-label-range', 'Showing projects {first}ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“{last} of {total}', { '{first}': position + 1, '{last}': Math.min(position + size, total), '{total}': total }));
        cards.forEach(function (card) {
            var index = matches.indexOf(card);
            card.inert = card.hidden || index < position || index >= position + size;
        });
    }
    function targetCard() {
        var hash = window.location.hash;
        return /^#project-[1-9][0-9]*$/.test(hash) ? document.getElementById(hash.slice(1)) : null;
    }
    function renderProjects() {
        closeProjectMenus();
        filters.forEach(function (button) { button.setAttribute('aria-pressed', button.dataset.evidenceFilter === selected ? 'true' : 'false'); });
        matches = cards.filter(function (card) { return selected === 'all' || (selected === 'sources' ? card.dataset.evidenceSourceCount === '0' : card.dataset.evidenceProjectStatus === selected); });
        cards.forEach(function (card) { if (!matches.includes(card)) card.hidden = true; });
        matches.forEach(function (card) { card.hidden = false; });
        if (filterEmpty) filterEmpty.hidden = matches.length !== 0;
        if (carousel) carousel.hidden = matches.length === 0;
        position = Math.min(position, maxPosition());
        updateNavigation();
        scrollToPosition(false);
    }
    function revealFragment() {
        var target = targetCard();
        if (!target || !cards.includes(target)) return;
        selected = 'all';
        position = 0;
        renderProjects();
        position = Math.min(matches.indexOf(target), maxPosition());
        updateNavigation();
        scrollToPosition(false);
        target.tabIndex = -1;
        window.requestAnimationFrame(function () {
            target.focus({ preventScroll: true });
            target.scrollIntoView({ block: 'center', inline: 'nearest', behavior: reducedMotion.matches ? 'instant' : 'smooth' });
        });
    }
    filters.forEach(function (button) {
        button.addEventListener('click', function () { selected = button.dataset.evidenceFilter; position = 0; renderProjects(); });
    });
    function move(delta) {
        var focusedArrow = document.activeElement === previous ? previous : (document.activeElement === next ? next : null);
        closeProjectMenus();
        position = Math.max(0, Math.min(maxPosition(), position + delta));
        updateNavigation();
        scrollToPosition(true);
        if (focusedArrow && focusedArrow.disabled) {
            var availableArrow = focusedArrow === next ? previous : next;
            if (availableArrow && !availableArrow.disabled) availableArrow.focus({ preventScroll: true });
        }
    }
    if (previous) previous.addEventListener('click', function () { move(-1); });
    if (next) next.addEventListener('click', function () { move(1); });
    if (navigation) navigation.addEventListener('keydown', function (event) {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        if (!event.target.closest('[data-evidence-carousel-navigation]')) return;
        event.preventDefault();
        move(event.key === 'Home' ? -matches.length : (event.key === 'End' ? matches.length : (event.key === 'ArrowLeft' ? -1 : 1)));
    });
    if (track) track.addEventListener('scroll', function () {
        if (matches.length === 0) return;
        // Focusing a fixed menu item can dispatch a deferred track scroll.
        // Keep its keyboard context until Escape or an explicit navigation.
        if (activeOptionMenu && activeOptionMenu.open && activeOptionMenu.contains(document.activeElement)) {
            lastTrackScroll = track.scrollLeft;
            return;
        }
        var pitch = matches.length > 1 ? matches[1].offsetLeft - matches[0].offsetLeft : 0;
        if (pitch > 0) {
            var nextPosition = Math.max(0, Math.min(maxPosition(), Math.round(track.scrollLeft / pitch)));
            if (nextPosition !== position) { position = nextPosition; updateNavigation(); }
        }
        if (Math.abs(track.scrollLeft - lastTrackScroll) > 1) closeProjectMenus();
        lastTrackScroll = track.scrollLeft;
    }, { passive: true });
    window.addEventListener('resize', function () { position = Math.min(position, maxPosition()); updateNavigation(); scrollToPosition(false); });
    window.addEventListener('hashchange', revealFragment);
    if (review) review.addEventListener('click', function () {
        selected = 'all'; position = 0; renderProjects();
        var section = document.getElementById('project-evidence');
        if (section) section.scrollIntoView({ block: 'start', behavior: reducedMotion.matches ? 'instant' : 'smooth' });
        if (matches[0]) { matches[0].tabIndex = -1; matches[0].focus({ preventScroll: true }); }
    });
    renderProjects();
    revealFragment();
}());

(function () {
    'use strict';
    // Evidence editor writing aids. Hint rules are rendered by
    // evidence_writing_guidance.php; this script only re-applies them while typing.
    var fields = Array.from(document.querySelectorAll('[data-evidence-writing-field]'));
    if (fields.length === 0) return;

    function compile(pattern) {
        try { return new RegExp(pattern, 'u'); } catch (error) { return null; }
    }
    function enhance(field) {
        var textarea = field.querySelector('textarea');
        if (!textarea) return;
        var insert = field.querySelector('[data-evidence-insert-starter]');
        var hints = Array.from(field.querySelectorAll('[data-evidence-writing-hint]')).map(function (element) {
            return { id: element.getAttribute('data-evidence-writing-hint'), element: element, pattern: compile(element.getAttribute('data-hint-pattern') || ''), whenPresent: element.getAttribute('data-hint-when') === 'present' };
        }).filter(function (hint) { return hint.pattern !== null; });
        var slotHint = hints.find(function (hint) { return hint.id === 'unfilled_slot'; });

        function update() {
            var value = textarea.value;
            var empty = value.trim() === '';
            hints.forEach(function (hint) { hint.element.hidden = empty || hint.pattern.test(value) !== hint.whenPresent; });
            // A starter never overwrites what the Owner has written.
            if (insert) insert.disabled = !empty;
        }
        if (insert) {
            insert.hidden = false;
            insert.addEventListener('click', function () {
                if (textarea.value.trim() !== '') return;
                var starter = insert.getAttribute('data-starter') || '';
                textarea.value = starter;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                update();
                textarea.focus();
                // Select the first slot so typing replaces it.
                var slot = slotHint && slotHint.pattern.exec(starter);
                if (slot) textarea.setSelectionRange(slot.index, slot.index + slot[0].length);
            });
        }
        textarea.addEventListener('input', update);
        update();
    }
    fields.forEach(enhance);
}());

(function () {
    'use strict';
    var editor = document.querySelector('[data-evidence-editor]');
    if (!editor) return;
    var form = editor.querySelector('[data-evidence-text-form]');
    var panels = Array.from(editor.querySelectorAll('[data-evidence-step-panel]'));
    var nav = editor.querySelector('.evidence-hub-writing-nav');
    var buttons = Array.from(nav.querySelectorAll('[data-evidence-step]'));
    var actions = editor.querySelector('.evidence-hub-step-actions');
    var previous = actions.querySelector('[data-evidence-previous]');
    var next = actions.querySelector('[data-evidence-next]');
    var index = Math.max(0, panels.findIndex(function (panel) { return panel.dataset.evidenceStepPanel === editor.dataset.initialPart; }));
    function show(position, focus) {
        index = Math.max(0, Math.min(panels.length - 1, position));
        panels.forEach(function (panel, current) { panel.hidden = current !== index; });
        buttons.forEach(function (button, current) {
            if (current === index) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current');
        });
        previous.disabled = index === 0; next.disabled = index === panels.length - 1;
        if (focus) { var heading = panels[index].querySelector('h2'); heading.tabIndex = -1; heading.focus(); }
    }
    buttons.forEach(function (button, current) { button.addEventListener('click', function () { show(current, true); }); });
    previous.addEventListener('click', function () { show(index - 1, true); });
    next.addEventListener('click', function () { show(index + 1, true); });
    nav.hidden = false; actions.hidden = false; show(index, false);
    var firstError = editor.querySelector('[aria-invalid="true"]');
    if (firstError) {
        var panelIndex = panels.indexOf(firstError.closest('[data-evidence-step-panel]'));
        show(panelIndex, false); window.setTimeout(function () { firstError.focus(); }, 0);
    }
    editor.querySelectorAll('.owner-error-summary a').forEach(function (link) {
        link.addEventListener('click', function () {
            var target = document.getElementById(link.hash.slice(1));
            var panel = target && target.closest('[data-evidence-step-panel]');
            if (panel) show(panels.indexOf(panel), false);
        });
    });
    var fields = Array.from(form.querySelectorAll('textarea'));
    var initial = fields.map(function (field) { return field.value; });
    var submitting = false;
    var status = form.querySelector('[data-evidence-dirty]');
    function dirty() { return editor.dataset.evidenceRejected === 'true' || fields.some(function (field, current) { return field.value !== initial[current]; }); }
    function updateStatus() { status.textContent = dirty() ? status.dataset.dirty : status.dataset.clean; }
    status.hidden = false; updateStatus();
    form.addEventListener('input', updateStatus);
    form.addEventListener('submit', function () { submitting = true; });
    window.addEventListener('beforeunload', function (event) {
        if (!submitting && dirty()) { event.preventDefault(); event.returnValue = ''; }
    });
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || link.hash && link.pathname === location.pathname && link.search === location.search || event.defaultPrevented || event.ctrlKey || event.metaKey || link.target === '_blank') return;
        if (!submitting && dirty()) {
            if (!window.confirm(form.dataset.leaveConfirm)) { event.preventDefault(); return; }
            submitting = true;
        }
    });
}());
