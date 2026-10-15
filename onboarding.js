(function () {
    'use strict';

    var root = document.getElementById('ather-onboarding-preview');
    if (!root) return;

    var locale = document.documentElement.lang === 'en' ? 'en' : 'ar';
    var panels = root.querySelectorAll('[data-step-panel]');
    var steps = root.querySelectorAll('[data-step-jump]');
    var back = root.querySelector('[data-back]');
    var next = root.querySelector('[data-next]');
    var create = root.querySelector('[data-create]');
    var languageSwitch = root.querySelector('[data-language-switch]');
    var languageStatus = root.querySelector('[data-language-status]');
    var previewToggle = root.querySelector('[data-preview-toggle]');
    var previewColumn = root.querySelector('[data-preview-column]');
    var nameInput = root.querySelector('[data-profile-name]');
    var countrySelect = root.querySelector('[data-country]');
    var statusSelect = root.querySelector('[data-career-status]');
    var fieldInput = root.querySelector('[data-field]');
    var headlineInput = root.querySelector('[data-headline]');
    var slugInput = root.querySelector('[data-slug]');
    var showEmail = root.querySelector('[data-show-email]');
    var showPhone = root.querySelector('[data-show-phone]');
    var currentStep = Math.min(5, Math.max(1, Number(root.dataset.initialStep) || 1));
    var switchingLanguage = false;
    var languageTimeoutMs = 5000;

    function fetchLocalizedPage(targetLocale, step) {
        var controller = new AbortController();
        var timeoutId;
        var timeout = new Promise(function (_resolve, reject) {
            timeoutId = window.setTimeout(function () {
                controller.abort();
                reject(new Error('Preview translation timed out'));
            }, languageTimeoutMs);
        });
        var request = Promise.resolve().then(function () {
            return fetch('?lang=' + targetLocale + '&step=' + step, {
                method: 'GET', credentials: 'omit', referrerPolicy: 'no-referrer', cache: 'no-store',
                signal: controller.signal
            });
        }).then(function (response) {
            if (!response.ok) throw new Error('Preview translation unavailable');
            return response.text();
        });
        return Promise.race([request, timeout]).finally(function () {
            window.clearTimeout(timeoutId);
        });
    }

    function directTextNodes(element) {
        return Array.from(element.childNodes).filter(function (node) { return node.nodeType === Node.TEXT_NODE; });
    }

    function applyLocalizedPage(page, targetLocale) {
        var sourceRoot = page.getElementById('ather-onboarding-preview');
        if (!sourceRoot || page.documentElement.lang !== targetLocale) throw new Error('Invalid preview translation');
        var currentElements = [root].concat(Array.from(root.querySelectorAll('*')));
        var translatedElements = [sourceRoot].concat(Array.from(sourceRoot.querySelectorAll('*')));
        if (currentElements.length !== translatedElements.length) throw new Error('Preview structure changed');
        var pairs = currentElements.map(function (element, index) {
            var translated = translatedElements[index];
            var currentText = directTextNodes(element);
            var translatedText = directTextNodes(translated);
            if (element.tagName !== translated.tagName || currentText.length !== translatedText.length) {
                throw new Error('Preview structure changed');
            }
            return { element: element, translated: translated, currentText: currentText, translatedText: translatedText };
        });
        var attributes = ['aria-label', 'title', 'placeholder', 'data-title', 'data-position',
            'data-open-label', 'data-close-label', 'data-preview-name', 'data-preview-country',
            'data-preview-status', 'data-preview-field', 'data-preview-headline', 'data-not-entered',
            'data-review-email-on', 'data-review-email-off', 'data-review-phone-on', 'data-review-phone-off',
            'data-error-message', 'lang', 'hreflang', 'href'];
        pairs.forEach(function (pair) {
            pair.currentText.forEach(function (node, index) { node.data = pair.translatedText[index].data; });
            attributes.forEach(function (name) {
                if (pair.translated.hasAttribute(name)) pair.element.setAttribute(name, pair.translated.getAttribute(name));
            });
        });
        document.querySelector('.ao-skip').textContent = page.querySelector('.ao-skip').textContent;
        document.title = page.title;
        document.documentElement.lang = targetLocale;
        document.documentElement.dir = targetLocale === 'ar' ? 'rtl' : 'ltr';
        locale = targetLocale;
        showStep(currentStep, false);
        updatePreview();
        previewToggle.textContent = previewColumn.classList.contains('is-open') ? previewToggle.dataset.closeLabel : previewToggle.dataset.openLabel;
    }

    function showStep(step, moveFocus) {
        var selected = Math.min(5, Math.max(1, step));
        currentStep = selected;
        for (var number = 1; number <= 5; number += 1) {
            root.classList.toggle('is-step-' + number, selected === number);
        }
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
        back.href = '?lang=' + locale + '&step=' + Math.max(1, selected - 1);
        next.hidden = selected === 5;
        next.href = '?lang=' + locale + '&step=' + Math.min(5, selected + 1);
        create.hidden = selected !== 5;
        languageSwitch.href = '?lang=' + (locale === 'ar' ? 'en' : 'ar') + '&step=' + selected;
        window.history.replaceState(null, '', '?lang=' + locale + '&step=' + selected);
        if (moveFocus) root.querySelector('#step-' + selected + '-heading').focus();
    }

    function updatePreview() {
        var name = nameInput.value.trim();
        var chosenCountry = countrySelect.value ? countrySelect.options[countrySelect.selectedIndex].textContent : '';
        var chosenStatus = statusSelect.value ? statusSelect.options[statusSelect.selectedIndex].textContent : '';
        var field = fieldInput.value.trim();
        var headline = headlineInput.value.trim();
        var slug = slugInput.value.trim();
        var selectedLanguage = root.querySelector('input[name="preview_preferred_language"]:checked');
        var profileLanguage = selectedLanguage ? selectedLanguage.parentElement.textContent.trim() : '';
        root.querySelector('[data-preview-name]').textContent = name || root.dataset.previewName;
        root.querySelector('[data-preview-initials]').textContent = name ? Array.from(name)[0] : 'أ';
        root.querySelector('[data-preview-country]').textContent = chosenCountry || root.dataset.previewCountry;
        root.querySelector('[data-preview-status]').textContent = chosenStatus || root.dataset.previewStatus;
        root.querySelector('[data-preview-field]').textContent = field || root.dataset.previewField;
        root.querySelector('[data-preview-headline]').textContent = headline || root.dataset.previewHeadline;
        root.querySelector('[data-review-identity]').textContent = [name, chosenCountry, profileLanguage].map(function (value) { return value || root.dataset.notEntered; }).join(' · ');
        root.querySelector('[data-review-profession]').textContent = [chosenStatus, field, headline].map(function (value) { return value || root.dataset.notEntered; }).join(' · ');
        root.querySelector('[data-review-slug]').textContent = slug ? 'ather.app/' + slug : root.dataset.notEntered;
        root.querySelector('[data-review-privacy]').textContent = [showEmail.checked ? root.dataset.reviewEmailOn : root.dataset.reviewEmailOff,
            showPhone.checked ? root.dataset.reviewPhoneOn : root.dataset.reviewPhoneOff].join(' · ');
    }

    root.querySelectorAll('[data-step-jump], [data-back], [data-next], [data-edit-step]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var destination = Number(link.dataset.stepJump || link.dataset.editStep ||
                (link.hasAttribute('data-back') ? currentStep - 1 : currentStep + 1));
            showStep(destination, true);
        });
    });
    languageSwitch.addEventListener('click', function (event) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (switchingLanguage) return;
        switchingLanguage = true;
        languageStatus.hidden = true;
        languageStatus.textContent = '';
        var targetLocale = locale === 'ar' ? 'en' : 'ar';
        Promise.resolve().then(function () {
            return fetchLocalizedPage(targetLocale, currentStep);
        }).then(function (html) {
            applyLocalizedPage(new DOMParser().parseFromString(html, 'text/html'), targetLocale);
        }).catch(function () {
            languageStatus.textContent = languageStatus.dataset.errorMessage;
            languageStatus.hidden = false;
        }).finally(function () { switchingLanguage = false; });
    });
    [nameInput, fieldInput, headlineInput, slugInput].forEach(function (input) { input.addEventListener('input', updatePreview); });
    [countrySelect, statusSelect, showEmail, showPhone].forEach(function (input) { input.addEventListener('change', updatePreview); });
    root.querySelectorAll('input[name="preview_preferred_language"]').forEach(function (input) {
        input.addEventListener('change', updatePreview);
    });
    previewToggle.addEventListener('click', function () {
        var open = previewColumn.classList.toggle('is-open');
        previewToggle.setAttribute('aria-expanded', String(open));
        previewToggle.textContent = open ? previewToggle.dataset.closeLabel : previewToggle.dataset.openLabel;
    });

    showStep(currentStep, false);
    updatePreview();
}());
