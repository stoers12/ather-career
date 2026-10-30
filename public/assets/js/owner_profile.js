/* Native anchors remain usable with every panel visible when JavaScript is absent. */
(function () {
    'use strict';
    var profile = document.querySelector('.owner-profile');
    if (!profile) return;
    var panels = Array.from(profile.querySelectorAll('[data-owner-profile-panel]'));
    var links = profile.querySelectorAll('[data-owner-profile-link]');
    var form = document.getElementById('profile-form');
    var save = profile.querySelector('[data-owner-profile-save]');
    function reveal(control) {
        var parent = control.parentElement;
        while (parent && parent !== profile) {
            if (parent.tagName === 'DETAILS') parent.open = true;
            parent = parent.parentElement;
        }
    }
    function show(id) {
        if (!panels.some(function (panel) { return panel.id === id; })) id = 'basics';
        panels.forEach(function (panel) { panel.hidden = panel.id !== id; });
        links.forEach(function (link) {
            if (link.getAttribute('data-owner-profile-link') === id) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        form.hidden = id === 'skills';
        save.hidden = id === 'skills';
    }
    function fromLocation() { show(window.location.hash.slice(1)); }
    window.addEventListener('hashchange', fromLocation);
    links.forEach(function (link) { link.addEventListener('click', function () { show(link.getAttribute('data-owner-profile-link')); }); });
    var invalid = profile.querySelector('[aria-invalid="true"]');
    if (invalid) {
        var panel = invalid.closest('[data-owner-profile-panel]');
        show(panel ? panel.id : 'basics');
        reveal(invalid);
        window.setTimeout(function () { invalid.focus(); }, 0);
    } else {
        var params = new URLSearchParams(window.location.search);
        var skillResult = ['skill_added', 'skill_updated', 'skill_deleted'].some(function (key) { return params.has(key); });
        show(skillResult ? 'skills' : window.location.hash.slice(1));
    }
    // Reveal hidden required controls before native validation tries to focus them.
    var revealingInvalid = false;
    form.addEventListener('invalid', function (event) {
        if (revealingInvalid) return;
        revealingInvalid = true;
        var panel = event.target.closest('[data-owner-profile-panel]');
        show(panel ? panel.id : 'basics');
        reveal(event.target);
        window.setTimeout(function () { revealingInvalid = false; }, 0);
    }, true);
}());
