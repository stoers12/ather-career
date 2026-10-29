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

    function applyTheme(value) {
        var theme = value === 'light' || value === 'dark' ? value : resolvedTheme();
        root.setAttribute('data-evidence-hub-theme', theme);
        // The redesigned workspace root (ather.css) reads its own attribute.
        var app = document.getElementById('ather-navigation');
        if (app) app.setAttribute('data-ath-theme', theme);
        if (typeof document.querySelectorAll === 'function') document.querySelectorAll('[data-owner-theme]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-owner-theme') === theme ? 'true' : 'false');
        });
    }

    applyTheme();
    // Restore a collapsed workspace sidebar before the first paint; admin.js handles the button.
    try {
        var workspace = document.getElementById('ather-navigation');
        if (workspace && window.localStorage.getItem('ather.sidebar.collapsed') === '1') workspace.setAttribute('data-sidebar-collapsed', 'true');
    } catch (error) { /* The sidebar stays expanded when storage is unavailable. */ }
    if (typeof document.addEventListener === 'function') document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-owner-theme]').forEach(function (button) {
            button.addEventListener('click', function () {
                var theme = button.getAttribute('data-owner-theme');
                if (theme !== 'light' && theme !== 'dark') return;
                try { window.localStorage.setItem(themeKey, theme); } catch (error) { /* Apply for this page when storage is unavailable. */ }
                applyTheme(theme);
            });
        });
        applyTheme();
    });
    if (systemTheme && typeof systemTheme.addEventListener === 'function') {
        systemTheme.addEventListener('change', function () {
            if (savedTheme() === null) applyTheme();
        });
    }
}());
