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

    var themePreference = savedTheme() || 'system';

    function applyTheme(value) {
        var theme = value === 'light' || value === 'dark' ? value : resolvedTheme();
        root.setAttribute('data-evidence-hub-theme', theme);
        if (typeof document.querySelectorAll === 'function') document.querySelectorAll('[data-owner-theme]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-owner-theme') === theme ? 'true' : 'false');
        });
        if (typeof document.querySelectorAll === 'function') document.querySelectorAll('[data-owner-appearance]').forEach(function (input) {
            input.checked = input.value === themePreference;
        });
    }

    function chooseTheme(value) {
        if (value !== 'system' && value !== 'light' && value !== 'dark') return;
        themePreference = value;
        try {
            if (value === 'system') window.localStorage.removeItem(themeKey);
            else window.localStorage.setItem(themeKey, value);
        } catch (error) { /* Apply for this page when storage is unavailable. */ }
        applyTheme(value === 'system' ? null : value);
    }

    var preferences = {
        text_size: { key: 'ather.owner.textSize', options: ['default', 'large'], fallback: 'default', attribute: 'data-owner-text-size' },
        motion: { key: 'ather.owner.motion', options: ['system', 'reduce'], fallback: 'system', attribute: 'data-owner-motion' }
    };
    function readPreference(setting) {
        try {
            var value = window.localStorage.getItem(setting.key);
            return setting.options.indexOf(value) >= 0 ? value : setting.fallback;
        } catch (error) { return setting.fallback; }
    }
    function applyPreference(name, value) {
        var setting = preferences[name];
        root.setAttribute(setting.attribute, value);
        document.querySelectorAll('[data-owner-preference="' + name + '"]').forEach(function (control) { control.value = value; });
    }
    function settingsSection() {
        if (!root.classList.contains('owner-settings-page')) return;
        var section = ['#help', '#data'].indexOf(window.location.hash) >= 0 ? window.location.hash.slice(1) : 'appearance';
        root.setAttribute('data-owner-settings-section', section);
        document.querySelectorAll('[data-owner-settings-panel]').forEach(function (panel) { panel.hidden = panel.getAttribute('data-owner-settings-panel') !== section; });
        document.querySelectorAll('[data-owner-settings-link]').forEach(function (link) {
            if (link.getAttribute('data-owner-settings-link') === section) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        document.querySelectorAll('.owner-nav-utilities .owner-nav-item').forEach(function (link) {
            var current = (link.hash === '#help') === (section === 'help');
            if (current) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
            link.classList.toggle('active', current);
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
    if (workspace) {
        Object.keys(preferences).forEach(function (name) { root.setAttribute(preferences[name].attribute, readPreference(preferences[name])); });
        root.setAttribute('data-owner-settings-section', ['#help', '#data'].indexOf(window.location.hash) >= 0 ? window.location.hash.slice(1) : 'appearance');
    }
    applyTheme();
    if (typeof document.addEventListener === 'function') document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-owner-theme]').forEach(function (button) {
            button.addEventListener('click', function () {
                var theme = button.getAttribute('data-owner-theme');
                if (theme !== 'light' && theme !== 'dark') return;
                chooseTheme(theme);
            });
        });
        document.querySelectorAll('[data-owner-sidebar-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                sidebarPreference = sidebarPreference === 'compact' ? 'expanded' : 'compact';
                try { window.localStorage.setItem(sidebarKey, sidebarPreference); } catch (error) { /* Retain this page's preference. */ }
                applySidebar();
            });
        });
        if (workspace) {
            document.querySelectorAll('[data-owner-browser-preference]').forEach(function (control) { control.hidden = false; });
            document.querySelectorAll('[data-owner-appearance]').forEach(function (control) { control.addEventListener('change', function () { if (control.checked) chooseTheme(control.value); }); });
            Object.keys(preferences).forEach(function (name) { applyPreference(name, readPreference(preferences[name])); });
            document.querySelectorAll('[data-owner-preference]').forEach(function (control) {
                control.addEventListener('change', function () {
                    var name = control.getAttribute('data-owner-preference');
                    var setting = preferences[name];
                    if (!setting || setting.options.indexOf(control.value) < 0) return;
                    try { window.localStorage.setItem(setting.key, control.value); } catch (error) { /* Keep this page usable without storage. */ }
                    applyPreference(name, control.value);
                });
            });
            settingsSection();
            window.addEventListener('hashchange', settingsSection);
        }
        applySidebar();
        applyTheme();
    });
    if (desktop && typeof desktop.addEventListener === 'function') desktop.addEventListener('change', applySidebar);
    if (systemTheme && typeof systemTheme.addEventListener === 'function') {
        systemTheme.addEventListener('change', function () {
            if (themePreference === 'system') applyTheme();
        });
    }
}());
