(function () {
    'use strict';

    var root = document.getElementById('ather-onboarding-preview');
    if (!root) return;

    var locale = document.documentElement.lang === 'en' ? 'en' : 'ar';
    var panels = root.querySelectorAll('[data-step-panel]');
    var steps = root.querySelectorAll('[data-step-jump]');
    var back = root.querySelector('[data-back]');
    var next = root.querySelector('[data-next]');
    var nextUnavailable = root.querySelector('[data-next-unavailable]');
    var nextNote = root.querySelector('[data-next-note]');
    var languageSwitch = root.querySelector('[data-language-switch]');
    var previewToggle = root.querySelector('[data-preview-toggle]');
    var previewColumn = root.querySelector('[data-preview-column]');
    var nameInput = root.querySelector('[data-profile-name]');
    var countrySelect = root.querySelector('[data-country]');

    function showStep(step, moveFocus) {
        var selected = step === 2 ? 2 : 1;
        root.classList.toggle('is-step-2', selected === 2);
        root.classList.toggle('is-step-1', selected === 1);
        panels.forEach(function (panel) {
            panel.hidden = Number(panel.dataset.stepPanel) !== selected;
        });
        steps.forEach(function (link) {
            var isCurrent = Number(link.dataset.stepJump) === selected;
            link.classList.toggle('is-current', isCurrent);
            if (isCurrent) {
                link.setAttribute('aria-current', 'step');
                root.querySelector('[data-progress-title]').textContent = link.dataset.title;
                root.querySelector('[data-progress-position]').textContent = link.dataset.position;
                root.querySelector('.ao-progress-shell').setAttribute('aria-label', link.dataset.title);
                root.querySelector('.ao-progress-steps').setAttribute('aria-label', link.dataset.title);
                root.querySelector('.ao-form-card').setAttribute('aria-label', link.textContent.trim());
            } else {
                link.removeAttribute('aria-current');
            }
        });
        back.hidden = selected === 1;
        next.hidden = selected === 2;
        nextUnavailable.hidden = selected === 1;
        nextNote.hidden = selected === 1;
        languageSwitch.href = '?lang=' + (locale === 'ar' ? 'en' : 'ar') + '&step=' + selected;
        window.history.replaceState(null, '', '?lang=' + locale + '&step=' + selected);
        if (moveFocus) root.querySelector('#step-' + selected + '-heading').focus();
    }

    function updatePreview() {
        var name = nameInput.value.trim();
        var chosenCountry = countrySelect.value ? countrySelect.options[countrySelect.selectedIndex].textContent : '';
        root.querySelector('[data-preview-name]').textContent = name || root.dataset.previewName;
        root.querySelector('[data-preview-initials]').textContent = name ? Array.from(name)[0] : 'أ';
        root.querySelector('[data-preview-country]').textContent = chosenCountry || root.dataset.previewCountry;
    }

    root.querySelectorAll('[data-step-jump], [data-back], [data-next]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            showStep(Number(link.dataset.stepJump || (link.hasAttribute('data-back') ? 1 : 2)), true);
        });
    });
    nameInput.addEventListener('input', updatePreview);
    countrySelect.addEventListener('change', updatePreview);
    previewToggle.addEventListener('click', function () {
        var open = previewColumn.classList.toggle('is-open');
        previewToggle.setAttribute('aria-expanded', String(open));
        previewToggle.textContent = open ? previewToggle.dataset.closeLabel : previewToggle.dataset.openLabel;
    });

    showStep(Number(root.dataset.initialStep), false);
}());
