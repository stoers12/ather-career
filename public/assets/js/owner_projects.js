/* Progressive enhancement: native cards, disclosures and one complete editor form. */
(function () {
    'use strict';
    var root = document.querySelector('.owner-projects');
    if (!root) return;
    var tools = root.querySelector('[data-project-tools]');
    if (tools) {
        var cards = Array.from(root.querySelectorAll('[data-project-card]'));
        var search = root.querySelector('#project-search');
        var filter = 'all';
        var normalize = function (value) { return value.normalize('NFKC').toLocaleLowerCase().trim(); };
        function applyFilters() {
            var query = normalize(search.value);
            var counts = { all: 0, published: 0, draft: 0 };
            var shown = 0;
            cards.forEach(function (card) {
                var matches = normalize(card.dataset.search).includes(query);
                if (matches) { counts.all++; counts[card.dataset.publication]++; }
                card.hidden = !matches || (filter !== 'all' && card.dataset.publication !== filter);
                if (!card.hidden) shown++;
                else card.querySelectorAll('details[open]').forEach(function (detail) { detail.open = false; });
            });
            Object.keys(counts).forEach(function (key) { root.querySelector('[data-project-count="' + key + '"]').textContent = counts[key]; });
            root.querySelector('[data-project-no-results]').hidden = shown !== 0 || cards.length === 0;
            var result = root.querySelector('[data-project-results]');
            result.textContent = result.dataset.label + ' ' + shown;
        }
        tools.hidden = false;
        root.querySelector('[data-project-results]').hidden = false;
        search.addEventListener('input', applyFilters);
        root.querySelectorAll('[data-project-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                filter = button.dataset.projectFilter;
                tools.querySelectorAll('[data-project-filter]').forEach(function (other) { other.setAttribute('aria-pressed', String(other === button)); });
                applyFilters();
            });
        });
        root.querySelectorAll('[data-project-view]').forEach(function (button) {
            button.addEventListener('click', function () {
                root.querySelector('[data-project-grid]').dataset.view = button.dataset.projectView;
                tools.querySelectorAll('[data-project-view]').forEach(function (other) { other.setAttribute('aria-pressed', String(other === button)); });
            });
        });
        root.querySelector('[data-project-reset]').addEventListener('click', function () {
            search.value = '';
            root.querySelector('[data-project-filter="all"]').click();
            search.focus();
        });
    }
    root.querySelectorAll('.owner-project-menu, .owner-project-publication').forEach(function (details) {
        details.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { details.open = false; details.querySelector('summary').focus(); event.stopPropagation(); }
        });
    });
    var form = root.querySelector('.owner-project-form');
    if (!form) return;
    var panels = Array.from(form.querySelectorAll('[data-project-step]'));
    var links = Array.from(root.querySelectorAll('[data-project-step-link]'));
    var next = form.querySelector('[data-project-next]');
    var previous = form.querySelector('[data-project-previous]');
    var current = 1;
    function showStep(number, focus) {
        current = number;
        panels.forEach(function (panel) { panel.hidden = Number(panel.dataset.projectStep) !== number; });
        links.forEach(function (link) {
            if (Number(link.dataset.projectStepLink) === number) link.setAttribute('aria-current', 'step');
            else link.removeAttribute('aria-current');
        });
        next.hidden = number === 3;
        previous.hidden = number === 1;
        if (focus) panels[number - 1].focus();
    }
    form.dataset.enhanced = 'true';
    links.forEach(function (link) { link.addEventListener('click', function (event) { event.preventDefault(); showStep(Number(link.dataset.projectStepLink), true); }); });
    next.addEventListener('click', function () { showStep(Math.min(3, current + 1), true); });
    previous.addEventListener('click', function () { showStep(Math.max(1, current - 1), true); });
    // Native validation must reveal a required field in an otherwise hidden step.
    var revealingInvalid = false;
    form.addEventListener('invalid', function (event) {
        if (revealingInvalid) return;
        revealingInvalid = true;
        window.setTimeout(function () { revealingInvalid = false; }, 0);
        var panel = event.target.closest('[data-project-step]');
        if (panel) { showStep(Number(panel.dataset.projectStep), false); }
    }, true);
    var error = form.querySelector('[aria-invalid="true"]');
    showStep(error ? Number(error.closest('[data-project-step]').dataset.projectStep) : 1, false);
    if (error) window.setTimeout(function () { error.focus(); }, 0);
    var preview = root.querySelector('[data-project-preview]');
    preview.hidden = false;
    function updatePreview() {
        ['title', 'category', 'description', 'technologies', 'live_url', 'github_url'].forEach(function (key) {
            var value = form.elements.namedItem(key).value;
            var review = root.querySelector('[data-project-review="' + key + '"]');
            review.textContent = value;
            var target = preview.querySelector('[data-project-preview-' + key + ']');
            if (!target) return;
            if (key !== 'technologies') { target.textContent = value || target.dataset.empty || ''; return; }
            target.replaceChildren();
            var technologies = value.split(/\r?\n/).map(function (item) { return item.trim(); }).filter(Boolean);
            technologies.slice(0, 3).forEach(function (item) { var tag = document.createElement('span'); tag.dir = 'auto'; tag.textContent = item; target.append(tag); });
            if (technologies.length > 3) { var more = document.createElement('span'); more.textContent = '+' + (technologies.length - 3); target.append(more); }
        });
    }
    form.addEventListener('input', updatePreview);
    updatePreview();
}());
