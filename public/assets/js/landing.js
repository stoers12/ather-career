(function () {
    'use strict';

    var root = document.documentElement;
    var themeKey = 'ather.evidenceHub.theme';
    var systemTheme = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function savedTheme() {
        try {
            var value = window.localStorage.getItem(themeKey);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (error) { return null; }
    }

    function applyTheme(theme) {
        root.setAttribute('data-landing-theme', theme);
        document.querySelectorAll('.hf-theme').forEach(function (button) {
            button.setAttribute('aria-pressed', String(theme === 'dark'));
        });
    }

    function resolveTheme() {
        return savedTheme() || (systemTheme && systemTheme.matches ? 'dark' : 'light');
    }

    // Run before the stylesheet loads to avoid flashing the wrong saved theme.
    applyTheme(resolveTheme());
    if (systemTheme && systemTheme.addEventListener) {
        systemTheme.addEventListener('change', function () {
            if (!savedTheme()) applyTheme(resolveTheme());
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var themeButtons = document.querySelectorAll('.hf-theme');
        themeButtons.forEach(function (button) {
            button.hidden = false;
            button.addEventListener('click', function () {
                var theme = root.getAttribute('data-landing-theme') === 'dark' ? 'light' : 'dark';
                try { window.localStorage.setItem(themeKey, theme); } catch (error) { /* The toggle still works without persistence. */ }
                applyTheme(theme);
            });
        });
        applyTheme(root.getAttribute('data-landing-theme'));

        var trigger = document.getElementById('hf-menu-trigger');
        var menu = document.getElementById('hf-mobile-menu');
        if (!trigger || !menu) return;
        trigger.hidden = false;

        function setMenu(open, restoreFocus) {
            menu.hidden = !open;
            trigger.setAttribute('aria-expanded', String(open));
            trigger.setAttribute('aria-label', open ? trigger.dataset.closeLabel : trigger.dataset.openLabel);
            if (restoreFocus) trigger.focus();
        }

        setMenu(false, false);
        trigger.addEventListener('click', function () {
            setMenu(trigger.getAttribute('aria-expanded') !== 'true', false);
        });
        menu.addEventListener('click', function (event) {
            var link = event.target.closest('a');
            if (!link) return;
            // Move focus with section navigation rather than leaving it in a hidden menu.
            if (link.hash) {
                var destination = document.getElementById(link.hash.slice(1));
                if (destination) {
                    destination.setAttribute('tabindex', '-1');
                    destination.focus();
                }
            }
            setMenu(false, false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !menu.hidden) {
                event.preventDefault();
                setMenu(false, true);
            }
        });
        var desktop = window.matchMedia ? window.matchMedia('(min-width: 721px)') : null;
        if (desktop && desktop.addEventListener) {
            desktop.addEventListener('change', function (event) {
                if (event.matches) {
                    var focusInMenu = menu.contains(document.activeElement);
                    setMenu(false, false);
                    if (focusInMenu) document.querySelector('.hf-nav-link').focus();
                }
            });
        }
    });
}());
