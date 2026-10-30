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
        if (typeof document.querySelectorAll === 'function') document.querySelectorAll('[data-owner-theme]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-owner-theme') === theme ? 'true' : 'false');
        });
    }

    // Keep the Owner rail preference with the existing appearance controller.
    var sidebarKey = 'ather.owner.sidebar';
    var sidebarPreference = 'expanded';
    try { if (window.localStorage.getItem(sidebarKey) === 'compact') sidebarPreference = 'compact'; } catch (error) { /* Expanded is the no-storage default. */ }
    var workspace = root.classList && root.classList.contains('owner-workspace-page');
    var desktop = null;
    try { if (workspace && typeof window.matchMedia === 'function') desktop = window.matchMedia('(min-width: 801px)'); } catch (error) { /* Keep the sidebar expanded. */ }
    var groupStates = new Map();
    function applySidebar() {
        if (!workspace) return;
        var compact = sidebarPreference === 'compact' && desktop && desktop.matches;
        root.setAttribute('data-owner-sidebar', compact ? 'compact' : 'expanded');
        document.querySelectorAll('[data-owner-sidebar-toggle]').forEach(function (button) {
            button.hidden = false;
            button.setAttribute('aria-expanded', String(!compact));
            button.setAttribute('aria-label', button.getAttribute(compact ? 'data-label-expand' : 'data-label-collapse'));
        });
        document.querySelectorAll('.owner-nav-group').forEach(function (group) {
            if (compact) {
                if (!groupStates.has(group)) groupStates.set(group, group.open);
                group.open = true;
            } else if (groupStates.has(group)) {
                group.open = groupStates.get(group);
                groupStates.delete(group);
            }
        });
    }
    // Applied before the shell paints; controls are connected after parsing.
    if (workspace) root.setAttribute('data-owner-sidebar', sidebarPreference === 'compact' && desktop && desktop.matches ? 'compact' : 'expanded');
    applyTheme();
    if (typeof document.addEventListener === 'function') document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-owner-theme]').forEach(function (button) {
            button.addEventListener('click', function () {
                var theme = button.getAttribute('data-owner-theme');
                if (theme !== 'light' && theme !== 'dark') return;
                try { window.localStorage.setItem(themeKey, theme); } catch (error) { /* Apply for this page when storage is unavailable. */ }
                applyTheme(theme);
            });
        });
        document.querySelectorAll('[data-owner-sidebar-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                sidebarPreference = sidebarPreference === 'compact' ? 'expanded' : 'compact';
                try { window.localStorage.setItem(sidebarKey, sidebarPreference); } catch (error) { /* Retain this page's preference. */ }
                applySidebar();
            });
        });
        applySidebar();
        applyTheme();
    });
    if (desktop && typeof desktop.addEventListener === 'function') desktop.addEventListener('change', applySidebar);
    if (systemTheme && typeof systemTheme.addEventListener === 'function') {
        systemTheme.addEventListener('change', function () {
            if (savedTheme() === null) applyTheme();
        });
    }
}());
