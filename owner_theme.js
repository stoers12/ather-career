(function () {
    'use strict';

    var root = document.body;
    if (!root) return;

    var themeKey = 'ather.evidenceHub.theme';
    var systemTheme = null;
    try {
        if (typeof window.matchMedia === 'function') systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    } catch (error) { /* Light remains available when the system preference cannot be read. */ }

    function savedTheme() {
        try {
            var value = window.localStorage.getItem(themeKey);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (error) {
            return null;
        }
    }

    function resolvedTheme() {
        var saved = savedTheme();
        if (saved !== null) return saved;
        try {
            return systemTheme && systemTheme.matches ? 'dark' : 'light';
        } catch (error) {
            return 'light';
        }
    }

    function applyTheme() {
        root.setAttribute('data-evidence-hub-theme', resolvedTheme());
    }

    applyTheme();
    if (systemTheme && typeof systemTheme.addEventListener === 'function') {
        systemTheme.addEventListener('change', function () {
            if (savedTheme() === null) applyTheme();
        });
    }
}());
