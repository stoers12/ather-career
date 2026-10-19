(function () {
    'use strict';
    var root = document.querySelector('[data-draft-editor]');
    if (!root) return;
    var endpoint = '/owner_onboarding_draft.php';
    var context = root.dataset.editorContext;
    var locale = document.documentElement.lang;
    var step = Number(root.dataset.initialStep);
    var requestedStep = new URLSearchParams(location.search).has('step');
    var returning = false;
    var sections = {
        public_identity: ['display_name', 'country_code', 'preferred_language'],
        professional_snapshot: ['professional_status', 'specialization', 'headline'],
        profile_identity: ['slug_candidate']
    };
    var keys = Object.values(sections).flat();
    var model = {};
    var baseline = {};
    var version = 0;
    var loaded = false;
    var busy = false;
    var locked = false;
    var pending = null;
    var state = 'loading';
    var leaving = false;
    var timeoutMs = 12000;
    function messages() { return JSON.parse(root.dataset.editorCopy); }
    function dirty(key) { return model[key] !== (baseline[key] === undefined ? '' : baseline[key]); }
    function hasEdits() { return keys.some(dirty); }
    function say(nextState) {
        state = nextState;
        root.dataset.saveState = state;
        var message = state === 'saved' && version === 0 ? messages().empty : messages()[state];
        root.querySelector('[data-save-status]').textContent = message;
        root.querySelector('[data-save-summary]').textContent = message;
        var blocked = busy || !loaded || locked || state === 'conflict' || pending !== null;
        root.querySelectorAll('[data-next], [data-save-exit]').forEach(function (button) { button.disabled = blocked; });
        root.querySelector('[data-retry]').hidden = locked || busy || (pending === null && loaded);
        root.querySelector('[data-recover]').hidden = locked || busy || !loaded || (state !== 'conflict' && pending === null);
        root.querySelector('[data-fresh-context]').hidden = !locked;
        root.querySelector('[data-language-switch]').setAttribute('aria-disabled', String(busy || locked));
        root.querySelector('.ao-form-card').setAttribute('aria-busy', String(busy));
        root.querySelectorAll('[data-step-jump], [data-back], [data-edit-step]').forEach(function (link) { link.setAttribute('aria-disabled', String(busy)); });
        root.querySelectorAll('[data-draft-field], [data-clear-language]').forEach(function (control) { control.disabled = !loaded || locked || (busy && state === 'loading'); });
    }
    function stopAuthentication() {
        locked = true; busy = false;
        say('auth');
        root.querySelector('[data-save-status]').focus();
    }
    async function request(url, options) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
        try {
            var response = await fetch(url, Object.assign({credentials: 'same-origin', cache: 'no-store', redirect: 'error', referrerPolicy: 'no-referrer', signal: controller.signal}, options));
            var body = await response.text();
            return {status: response.status, ok: response.ok, json: async function () { return JSON.parse(body); }, text: async function () { return body; }};
        } finally { clearTimeout(timer); }
    }
    function controls(key) { return Array.from(root.querySelectorAll('[data-draft-field="' + key + '"]')); }
    function choiceText(key, value) {
        return (baseline[key] === value ? messages().older_choice : messages().unlisted_choice) + value;
    }
    function hydrateControls() {
        root.querySelectorAll('[data-extra-choice]').forEach(function (option) { option.remove(); });
        keys.forEach(function (key) {
            var value = model[key] || '';
            var list = controls(key);
            if (key === 'preferred_language') {
                if (value && !list.some(function (input) { return input.value === value; })) {
                    var label = document.createElement('label'); label.className = 'ao-radio-card'; label.dataset.extraChoice = '';
                    var radio = document.createElement('input'); radio.type = 'radio'; radio.name = 'preview_preferred_language';
                    radio.value = value; radio.dataset.draftField = key;
                    label.append(radio, document.createTextNode(choiceText(key, value)));
                    root.querySelector('.ao-language-options').append(label); list.push(radio);
                    radio.addEventListener('change', edit);
                }
                list.forEach(function (radio) { radio.checked = radio.value === value; });
            } else {
                var control = list[0];
                if (control.tagName === 'SELECT' && value && !Array.from(control.options).some(function (option) { return option.value === value; })) {
                    var option = document.createElement('option'); option.value = value; option.textContent = choiceText(key, value); option.dataset.extraChoice = '';
                    control.append(option);
                }
                control.value = value;
            }
        });
        updatePreview();
    }
    function selectedText(key) {
        var control = controls(key)[0];
        return control.tagName === 'SELECT' && control.selectedIndex >= 0 ? control.options[control.selectedIndex].textContent : model[key];
    }
    function updatePreview() {
        var country = model.country_code ? selectedText('country_code') : '';
        var status = model.professional_status ? selectedText('professional_status') : '';
        var language = controls('preferred_language').find(function (radio) { return radio.checked; });
        var languageText = language ? language.parentElement.textContent : '';
        var entries = {
            'preview-name': model.display_name || root.dataset.previewName,
            'preview-initials': model.display_name ? Array.from(model.display_name)[0] : 'أ',
            'preview-country': country || root.dataset.previewCountry,
            'preview-status': status || root.dataset.previewStatus,
            'preview-field': model.specialization || root.dataset.previewField,
            'preview-headline': model.headline || root.dataset.previewHeadline,
            'review-identity': [model.display_name, country, languageText].map(function (value) { return value || root.dataset.notEntered; }).join(' · '),
            'review-profession': [status, model.specialization, model.headline].map(function (value) { return value || root.dataset.notEntered; }).join(' · '),
            'review-slug': model.slug_candidate || root.dataset.notEntered
        };
        Object.entries(entries).forEach(function (entry) { root.querySelector('[data-' + entry[0] + ']').textContent = entry[1]; });
    }
    function showStep(destination, focus) {
        step = Math.min(5, Math.max(1, destination));
        for (var i = 1; i <= 5; i++) root.classList.toggle('is-step-' + i, i === step);
        root.querySelectorAll('[data-step-panel]').forEach(function (panel) { panel.hidden = Number(panel.dataset.stepPanel) !== step; });
        root.querySelectorAll('[data-step-jump]').forEach(function (link) {
            var current = Number(link.dataset.stepJump) === step;
            link.classList.toggle('is-current', current);
            if (current) {
                link.setAttribute('aria-current', 'step');
                root.querySelector('[data-progress-title]').textContent = link.dataset.title;
                root.querySelector('[data-progress-position]').textContent = link.dataset.position;
                root.querySelector('.ao-form-card').setAttribute('aria-label', link.textContent);
            } else link.removeAttribute('aria-current');
        });
        root.querySelector('[data-back]').hidden = step === 1;
        root.querySelector('[data-back]').href = '?lang=' + locale + '&step=' + Math.max(1, step - 1);
        root.querySelector('[data-next]').hidden = step === 5;
        root.querySelector('[data-create]').hidden = step !== 5;
        root.querySelector('[data-language-switch]').href = '?lang=' + (locale === 'ar' ? 'en' : 'ar') + '&step=' + step;
        history.replaceState(null, '', '?lang=' + locale + '&step=' + step);
        if (focus) root.querySelector('#step-' + step + '-heading').focus();
    }
    function edit(event) {
        if (!loaded || locked || (busy && state === 'loading')) return;
        var input = event.target;
        if (input.type === 'radio' && !input.checked) return;
        model[input.dataset.draftField] = input.value;
        updatePreview();
        if (!busy && pending === null && state !== 'conflict') say(hasEdits() ? 'unsaved' : 'saved');
    }
    async function resume(replace) {
        if (busy || locked) return;
        if (replace && !window.confirm(messages().replace_confirm)) return;
        busy = true; say('loading');
        try {
            var response = await request(endpoint, {method: 'GET'});
            if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
            if (!response.ok) throw new Error('unavailable');
            var data = await response.json();
            if (data.csrf_token !== context) { stopAuthentication(); return; }
            if (!data.success || !data.draft || !Number.isInteger(data.draft.version)) throw new Error('unavailable');
            baseline = data.draft.fields;
            version = data.draft.version;
            keys.forEach(function (key) { model[key] = baseline[key] === undefined ? '' : baseline[key]; });
            pending = null; loaded = true;
            hydrateControls();
            if (!replace && !requestedStep) {
                step = {public_identity: 2, professional_snapshot: 3, profile_identity: 4}[data.draft.last_edited_section] || step;
            }
            showStep(step, false);
            busy = false; say('saved');
        } catch (_error) { busy = false; say(pending ? 'uncertain' : 'unavailable'); }
    }
    function operation(section) {
        var fields = {};
        sections[section].forEach(function (key) { if (dirty(key)) fields[key] = model[key] === '' ? null : model[key]; });
        var bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
        var id = version + ':' + Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
        return JSON.stringify({expectedVersion: version, operationId: id, section: section, fields: fields});
    }
    async function save(destination, retry) {
        if (busy || locked || !loaded || state === 'conflict' || (pending && !retry)) return;
        busy = true; say('saving');
        try {
            while (true) {
                if (pending === null) {
                    var section = Object.keys(sections).find(function (name) { return sections[name].some(dirty); });
                    if (!section) break;
                    pending = operation(section);
                }
                // Immutable string retained across timeouts/uncertain outcomes.
                // Never substitute edited input into an existing operation ID.
                var payload = pending;
                var response = await request(endpoint, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': context}, body: payload});
                if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
                if (response.status === 409) { pending = null; busy = false; say('conflict'); root.querySelector('[data-save-status]').focus(); return; }
                if (response.status === 400 || response.status === 413 || response.status === 415) { pending = null; busy = false; say('invalid'); return; }
                if (!response.ok) throw new Error('unavailable');
                var data = await response.json();
                if (!data.success || !data.draft || data.draft.version !== JSON.parse(payload).expectedVersion + 1) throw new Error('unavailable');
                baseline = data.draft.fields; version = data.draft.version; pending = null;
                // Do not hydrate here: edits made after dispatch stay in model
                // and on screen, and are serialized in the next necessary save.
            }
            busy = false; say('saved');
            if (destination === 'exit') { leaving = true; location.assign('/owner.php'); }
            else if (typeof destination === 'number') showStep(destination, true);
        } catch (_error) { busy = false; say(pending ? 'uncertain' : 'unavailable'); }
    }
    async function switchLanguage(event) {
        event.preventDefault();
        if (busy || locked) return;
        busy = true; say(state);
        var nextLocale = locale === 'ar' ? 'en' : 'ar';
        try {
            var response = await request('/owner_onboarding_editor.php?lang=' + nextLocale + '&step=' + step, {method: 'GET'});
            if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
            if (!response.ok) throw new Error('unavailable');
            var page = new DOMParser().parseFromString(await response.text(), 'text/html');
            var nextRoot = page.querySelector('[data-draft-editor]');
            if (!nextRoot || nextRoot.dataset.editorContext !== context) { stopAuthentication(); return; }
            var imported = document.importNode(nextRoot, true);
            root.replaceWith(imported); root = imported;
            document.documentElement.lang = nextLocale; document.documentElement.dir = nextLocale === 'ar' ? 'rtl' : 'ltr';
            document.title = page.title; document.querySelector('.ao-skip').textContent = page.querySelector('.ao-skip').textContent;
            locale = nextLocale; bind(); hydrateControls(); showStep(step, false);
            root.querySelector('.hf-theme').hidden = false;
            root.querySelector('.hf-theme').setAttribute('aria-pressed', String(document.documentElement.dataset.landingTheme === 'dark'));
            busy = false; say(state); root.querySelector('[data-language-switch]').focus();
        } catch (_error) { busy = false; say(pending ? 'uncertain' : 'unavailable'); }
    }
    function bind() {
        root.querySelectorAll('[data-draft-field]').forEach(function (control) { control.addEventListener(control.tagName === 'SELECT' || control.type === 'radio' ? 'change' : 'input', edit); });
        root.querySelector('[data-clear-language]').addEventListener('click', function () { model.preferred_language = ''; hydrateControls(); if (!busy && !locked && pending === null && state !== 'conflict') say(hasEdits() ? 'unsaved' : 'saved'); });
        root.querySelectorAll('[data-step-jump], [data-back], [data-edit-step]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                if (busy) return;
                returning = link.hasAttribute('data-edit-step');
                showStep(returning ? Number(link.dataset.editStep) : link.hasAttribute('data-back') ? step - 1 : Number(link.dataset.stepJump), true);
            });
        });
        root.querySelector('[data-next]').addEventListener('click', function () { var to = returning ? 5 : step + 1; returning = false; save(to, false); });
        root.querySelectorAll('[data-save-exit]').forEach(function (button) { button.addEventListener('click', function () { save('exit', false); }); });
        root.querySelector('[data-retry]').addEventListener('click', function () { if (!loaded) resume(false); else save(null, true); });
        root.querySelector('[data-recover]').addEventListener('click', function () { resume(true); });
        root.querySelector('[data-fresh-context]').addEventListener('click', function (event) { if (!window.confirm(messages().fresh_confirm)) event.preventDefault(); else leaving = true; });
        root.querySelector('[data-language-switch]').addEventListener('click', switchLanguage);
        root.querySelector('[data-preview-toggle]').addEventListener('click', function () {
            var open = root.querySelector('[data-preview-column]').classList.toggle('is-open');
            this.setAttribute('aria-expanded', String(open)); this.textContent = open ? this.dataset.closeLabel : this.dataset.openLabel;
        });
        // On the initial document landing.js owns this button. Imported localized
        // markup needs a listener without storing any private editor state.
        if (loaded) root.querySelector('.hf-theme').addEventListener('click', function () {
            var dark = document.documentElement.dataset.landingTheme !== 'dark';
            document.documentElement.dataset.landingTheme = dark ? 'dark' : 'light'; this.setAttribute('aria-pressed', String(dark));
        });
    }
    window.addEventListener('beforeunload', function (event) { if (!leaving && (hasEdits() || pending || busy)) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pageshow', function (event) { if (event.persisted) stopAuthentication(); });
    keys.forEach(function (key) { model[key] = ''; });
    bind(); showStep(step, false); say('loading'); resume(false);
}());
