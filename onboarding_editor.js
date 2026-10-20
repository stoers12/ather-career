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
    var contextEpoch = 0;
    var switchingLanguage = false;
    var attestationListeners = null;
    var attestationEndpoint = '/owner_onboarding_attestation.php';
    var attestation = {sequence: 0, controller: null, token: null, state: 'loading', recordedAt: null};
    var completionEndpoint = '/owner_onboarding_completion.php';
    var completionFields = ['display_name', 'country_code', 'preferred_language', 'professional_status', 'specialization', 'headline', 'slug_candidate', 'name_ar', 'name_en', 'city', 'university', 'graduation_year', 'biography', 'phone'];
    var completion = {sequence: 0, controller: null, state: 'loading', review: null, receipt: null, intent: null};
    function completionBusy() { return ['loading', 'reviewing', 'completing'].includes(completion.state); }
    function completionControls() {
        var available = loaded && !locked && !busy && !switchingLanguage && !completionBusy() && attestation.state !== 'recording';
        var ready = available && completion.state === 'ready' && completion.review !== null && completion.review.expectedVersion === version
            && !hasEdits() && pending === null && state === 'saved' && !completion.intent && !completion.receipt;
        root.dataset.completionState = completion.state;
        root.querySelector('.ao-completion').setAttribute('aria-busy', String(completionBusy() || completion.state === 'saving'));
        root.querySelector('[data-completion-review]').disabled = !available || pending !== null || state === 'conflict' || !!completion.intent || !!completion.receipt;
        root.querySelector('[data-completion-read]').disabled = locked || switchingLanguage || completionBusy();
        root.querySelectorAll('[data-completion-confirm]').forEach(function (control) { control.disabled = !ready; });
        var create = root.querySelector('[data-create]');
        create.disabled = !ready || !Array.from(root.querySelectorAll('[data-completion-confirm]')).every(function (control) { return control.checked; });
        create.removeAttribute('title');
        root.querySelector('[data-completion-status]').textContent = messages()['completion_' + completion.state]
            + (completion.state === 'completed' && completion.receipt ? completion.receipt.completed_at : '');
        root.querySelector('[data-completion-retained]').hidden = completion.state !== 'completed';
        root.querySelector('[data-completion-review-body]').setAttribute('aria-busy', String(completion.state === 'reviewing'));
        root.querySelector('[data-language-switch]').setAttribute('aria-disabled', String(busy || locked || attestation.state === 'recording' || completion.state === 'completing'));
    }
    function retireCompletion(nextState) {
        completion.sequence++;
        if (completion.controller) completion.controller.abort();
        completion.controller = null; completion.review = null;
        // Never rewrite/discard an unconfirmed dispatched intent during invalidation.
        completion.state = locked ? 'auth' : completion.receipt ? 'completed' : completion.intent && nextState === 'idle' ? 'uncertain' : nextState;
        root.querySelector('[data-completion-review-body]').replaceChildren();
        root.querySelector('[data-completion-review-body]').hidden = true;
        root.querySelectorAll('[data-completion-confirm]').forEach(function (control) { control.checked = false; });
        completionControls();
    }
    function invalidateCompletion() {
        if (completion.state === 'completing') return;
        retireCompletion('idle');
    }
    function exactReceipt(receipt) {
        if (!receipt || !['portfolio_id', 'profile_id', 'draft_version', 'initial_profile_version'].every(function (key) { return Number.isInteger(receipt[key]) && receipt[key] > 0; })
            || receipt.initial_profile_version !== 1 || typeof receipt.contract_revision !== 'string' || typeof receipt.catalog_revision !== 'string'
            || !Array.isArray(receipt.deferred_fields) || !receipt.deferred_fields.every(function (key) { return completionFields.slice(7).includes(key); })
            || typeof receipt.completed_at !== 'string' || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/.test(receipt.completed_at)) throw new Error('unconfirmed');
        return receipt;
    }
    function confirmReceipt(receipt) {
        completion.receipt = exactReceipt(receipt); completion.intent = null;
        retireCompletion('completed');
        // Do not hydrate from a historical receipt or acknowledge pending/local edits.
        busy = false; say('completed');
        root.querySelector('[data-completion-status]').focus();
    }
    async function readCompletionReceipt() {
        if (locked || switchingLanguage || completionBusy()) return;
        retireCompletion('loading');
        var sequence = completion.sequence, epoch = contextEpoch, node = root;
        completion.controller = new AbortController();
        function active() { return current(epoch, node) && sequence === completion.sequence; }
        try {
            var response = await request(completionEndpoint, {method: 'GET'}, completion.controller);
            if (!active()) return;
            if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
            if (response.status === 404) { retireCompletion(completion.intent ? 'uncertain' : 'unavailable'); return; }
            var data = await response.json();
            if (!active()) return;
            if (!response.ok || data.success !== true || !Object.hasOwn(data, 'receipt')) throw new Error('unavailable');
            if (data.csrf_token !== context) { stopAuthentication(); return; }
            if (data.receipt !== null) { confirmReceipt(data.receipt); return; }
            retireCompletion(completion.intent ? 'absent' : 'idle');
        } catch (_error) { if (active()) retireCompletion(completion.intent ? 'uncertain' : 'temporary'); }
    }
    function presentCompletionReview(review) {
        if (!review || review.expectedVersion !== version || typeof review.reviewToken !== 'string' || !/^[a-f0-9]{64}$/.test(review.reviewToken)
            || !review.fields || typeof review.fields !== 'object' || Array.isArray(review.fields) || typeof review.disposition !== 'string'
            || !Array.isArray(review.deferred_fields) || !review.deferred_fields.every(function (key) { return completionFields.slice(7).includes(key); })
            || !Object.entries(review.fields).every(function (entry) { return completionFields.includes(entry[0]) && typeof entry[1] === 'string'; })) throw new Error('unavailable');
        var fragment = document.createDocumentFragment();
        [completionFields.slice(0, 7), completionFields.slice(7)].forEach(function (fields, index) {
            var heading = document.createElement('h2'); heading.textContent = messages()[index ? 'completion_deferred' : 'completion_initial']; fragment.append(heading);
            var list = document.createElement('dl'); list.className = 'ao-completion-review-values';
            fields.forEach(function (key) {
                var group = document.createElement('div'), label = document.createElement('dt'), value = document.createElement('dd');
                label.textContent = messages()['completion_field_' + key];
                value.dataset.completionField = key; value.className = 'ao-completion-value'; value.dir = 'auto';
                value.textContent = Object.hasOwn(review.fields, key) ? review.fields[key] : messages().completion_absent_field;
                group.append(label, value); list.append(group);
            }); fragment.append(list);
        });
        var disposition = document.createElement('p'); disposition.dataset.completionDisposition = ''; disposition.className = 'ao-completion-value';
        disposition.dir = 'auto'; disposition.textContent = review.disposition; fragment.append(disposition);
        var editing = document.createElement('p'); editing.textContent = messages().completion_optional_edit; fragment.append(editing);
        root.querySelector('[data-completion-review-body]').replaceChildren(fragment);
        root.querySelector('[data-completion-review-body]').hidden = false;
        completion.review = review; completion.state = 'ready'; completionControls();
        root.querySelector('[data-completion-status]').focus();
    }
    function completionError(response, data) {
        if (response.status === 401 || data.code === 'invalid_csrf') { stopAuthentication(); return; }
        // A POST access_denied can be readiness/exact evidence, not session expiry.
        retireCompletion(data.code === 'access_denied' ? 'prerequisites' : data.code === 'saved_fields_invalid' ? 'invalid'
            : response.status === 409 ? 'conflict' : response.status === 404 || data.code === 'prerequisites_unavailable' ? 'unavailable' : 'temporary');
    }
    async function prepareCompletionReview() {
        if (!loaded || locked || busy || completionBusy() || switchingLanguage || pending !== null || state === 'conflict' || completion.intent || completion.receipt || attestation.state === 'recording') return;
        var entryEpoch = contextEpoch, entryRoot = root;
        invalidateCompletion(); completion.state = 'saving'; completionControls();
        await save(null, false);
        if (!current(entryEpoch, entryRoot)) return;
        if (locked || busy || !loaded || switchingLanguage || pending !== null || state !== 'saved' || hasEdits() || completion.intent || completion.receipt) { completionControls(); return; }
        retireCompletion('reviewing');
        var sequence = completion.sequence, epoch = contextEpoch, node = root, savedVersion = version;
        completion.controller = new AbortController();
        function active() { return current(epoch, node) && sequence === completion.sequence && !hasEdits() && version === savedVersion && pending === null; }
        try {
            var response = await request(completionEndpoint, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': context}, body: JSON.stringify({action: 'review', expectedVersion: savedVersion})}, completion.controller);
            if (!active()) return;
            var data = await response.json(); if (!active()) return;
            if (!response.ok || data.success !== true) { completionError(response, data); return; }
            presentCompletionReview(data.review);
        } catch (_error) { if (active()) retireCompletion('temporary'); }
    }
    async function createPrivateProfile() {
        if (root.querySelector('[data-create]').disabled || completion.state !== 'ready' || !completion.review || completion.intent || completion.receipt || locked || busy || switchingLanguage || pending || hasEdits() || completion.review.expectedVersion !== version
            || attestation.state === 'recording' || !Array.from(root.querySelectorAll('[data-completion-confirm]')).every(function (control) { return control.checked; })) return;
        var bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
        completion.intent = JSON.stringify({action: 'complete', expectedVersion: completion.review.expectedVersion,
            operationId: completion.review.expectedVersion + ':' + Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join(''),
            reviewToken: completion.review.reviewToken, confirm_private_creation: true, acknowledge_private_draft: true});
        var payload = completion.intent;
        retireCompletion('completing');
        var sequence = completion.sequence, epoch = contextEpoch, node = root;
        completion.controller = new AbortController(); say(state);
        function active() { return current(epoch, node) && sequence === completion.sequence; }
        try {
            var response = await request(completionEndpoint, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': context}, body: payload}, completion.controller);
            if (!active()) return;
            var data = await response.json(); if (!active()) return;
            if (response.status === 401 || data.code === 'invalid_csrf') { stopAuthentication(); return; }
            if (!response.ok || data.success !== true) {
                // Only a first, explicitly rejected request can retire its intent.
                // A lost/uncertain outcome retains immutable bytes; this UI offers no POST replay.
                if (['access_denied', 'saved_fields_invalid', 'review_conflict', 'prerequisites_unavailable', 'unavailable', 'invalid_input'].includes(data.code)) completion.intent = null;
                completionError(response, data);
                if (completion.intent) retireCompletion('uncertain');
                say(state); return;
            }
            confirmReceipt(data.receipt);
        } catch (_error) { if (active()) { retireCompletion('uncertain'); say(state); } }
    }
    function current(epoch, node) { return !locked && epoch === contextEpoch && node === root && node.isConnected; }
    function messages() { return JSON.parse(root.dataset.editorCopy); }
    function dirty(key) { return model[key] !== (baseline[key] === undefined ? '' : baseline[key]); }
    function hasEdits() { return keys.some(dirty); }
    function say(nextState) {
        state = nextState === 'auth' ? 'auth' : completion.receipt ? 'completed' : nextState;
        root.dataset.saveState = state;
        var message = state === 'completed' ? messages().completion_retained : state === 'saved' && version === 0 ? messages().empty : messages()[state];
        root.querySelector('[data-save-status]').textContent = message;
        root.querySelector('[data-save-summary]').textContent = message;
        var blocked = busy || !loaded || locked || state === 'conflict' || pending !== null || !!completion.intent || !!completion.receipt;
        root.querySelectorAll('[data-next], [data-save-exit]').forEach(function (button) { button.disabled = blocked; });
        root.querySelector('[data-retry]').hidden = !!completion.intent || !!completion.receipt || locked || busy || (pending === null && loaded);
        root.querySelector('[data-recover]').hidden = !!completion.intent || !!completion.receipt || locked || busy || !loaded || (state !== 'conflict' && pending === null);
        root.querySelector('[data-fresh-context]').hidden = !locked;
        root.querySelector('[data-language-switch]').setAttribute('aria-disabled', String(busy || locked || attestation.state === 'recording' || completion.state === 'completing'));
        root.querySelector('.ao-form-card').setAttribute('aria-busy', String(busy));
        root.querySelectorAll('[data-step-jump], [data-back], [data-edit-step]').forEach(function (link) { link.setAttribute('aria-disabled', String(busy)); });
        root.querySelectorAll('[data-draft-field], [data-clear-language]').forEach(function (control) { control.disabled = !loaded || locked || !!completion.receipt || completion.state === 'completing' || (busy && state === 'loading'); });
        completionControls();
    }
    function stopAuthentication() {
        contextEpoch++;
        if (attestationListeners) attestationListeners.abort();
        locked = true; busy = false;
        retireAttestation('auth'); retireCompletion('auth');
        say('auth');
        root.querySelector('[data-save-status]').focus();
    }
    async function request(url, options, controller) {
        controller = controller || new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
        try {
            var response = await fetch(url, Object.assign({credentials: 'same-origin', cache: 'no-store', redirect: 'error', referrerPolicy: 'no-referrer', signal: controller.signal}, options));
            var body = await response.text();
            return {status: response.status, ok: response.ok, json: async function () { return JSON.parse(body); }, text: async function () { return body; }};
        } finally { clearTimeout(timer); }
    }
    function attestationControls() {
        var ready = completion.state !== 'completing' && attestation.state === 'ready' && attestation.token !== null && loaded && !locked && !switchingLanguage;
        root.dataset.attestationState = attestation.state;
        root.querySelectorAll('[data-attestation-action]').forEach(function (input) { input.disabled = !ready; });
        root.querySelector('[data-attestation-record]').disabled = !ready || !Array.from(root.querySelectorAll('[data-attestation-action]')).every(function (input) { return input.checked; });
        var load = root.querySelector('[data-attestation-load]');
        load.disabled = completion.state === 'completing' || !loaded || locked || switchingLanguage || attestation.state === 'loading' || attestation.state === 'recording';
        load.textContent = messages()[attestation.state === 'uncertain' ? 'att_reread' : 'att_load'];
        root.querySelector('[data-attestation-status]').textContent = messages()['att_' + attestation.state] + (attestation.state === 'recorded' ? attestation.recordedAt || '' : '');
        root.querySelector('[data-language-switch]').setAttribute('aria-disabled', String(busy || locked || attestation.state === 'recording' || completion.state === 'completing'));
        root.querySelector('[data-attestation-documents]').setAttribute('aria-busy', String(attestation.state === 'loading' || attestation.state === 'recording'));
        completionControls();
    }
    function retireAttestation(nextState) {
        // Document context invalidates new-creation review, not an independent historical receipt read.
        if (completion.review || completion.state === 'reviewing') invalidateCompletion();
        attestation.sequence++;
        if (attestation.controller) attestation.controller.abort();
        attestation.controller = null; attestation.token = null; attestation.recordedAt = null; attestation.state = nextState;
        root.querySelectorAll('[data-attestation-action]').forEach(function (input) { input.checked = false; });
        root.querySelector('[data-attestation-documents]').replaceChildren();
        attestationControls();
    }
    function exactEvidence(evidence) {
        if (!evidence || typeof evidence.recorded !== 'boolean' || (evidence.recorded
            ? typeof evidence.recorded_at !== 'string' || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/.test(evidence.recorded_at)
            : evidence.recorded_at !== null)) throw new Error('unavailable');
        return evidence;
    }
    function presentDocuments(documents) {
        var fragment = document.createDocumentFragment();
        ['age', 'terms', 'privacy'].forEach(function (kind) {
            var doc = documents && documents[kind];
            if (!doc || typeof doc.content !== 'string' || doc.content === '' || typeof doc.language !== 'string'
                || !/^[A-Za-z0-9-]{2,35}$/.test(doc.language)) throw new Error('unavailable');
            var article = document.createElement('article'); article.className = 'ao-attestation-document';
            var title = document.createElement('h2'); title.textContent = messages()['att_' + kind + '_document'];
            var language = document.createElement('p'); language.textContent = messages().att_language + doc.language;
            var text = document.createElement('div'); text.className = 'ao-document-text';
            text.lang = doc.language; text.dir = 'auto'; text.textContent = doc.content;
            article.append(title, language, text); fragment.append(article);
        });
        root.querySelector('[data-attestation-documents]').replaceChildren(fragment);
    }
    async function loadAttestations() {
        if (!loaded || locked || switchingLanguage || completion.state === 'completing' || attestation.state === 'recording') return;
        retireAttestation('loading');
        var sequence = attestation.sequence, node = root, epoch = contextEpoch;
        attestation.controller = new AbortController();
        function active() { return current(epoch, node) && sequence === attestation.sequence; }
        try {
            var response = await request(attestationEndpoint, {method: 'GET'}, attestation.controller);
            if (!active()) return;
            if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
            var data = await response.json();
            if (!active()) return;
            if (response.status === 404 || data.code === 'policies_unavailable') { retireAttestation('unavailable'); return; }
            if (!response.ok || data.success !== true) throw new Error('unavailable');
            if (data.csrf_token !== context) { stopAuthentication(); return; }
            if (typeof data.presentation_token !== 'string' || !/^[a-f0-9]{64}$/.test(data.presentation_token)) throw new Error('unavailable');
            var evidence = exactEvidence(data.evidence);
            presentDocuments(data.documents);
            attestation.token = data.presentation_token;
            attestation.recordedAt = evidence.recorded_at;
            attestation.state = evidence.recorded ? 'recorded' : 'ready';
            attestationControls();
        } catch (_error) { if (active()) retireAttestation('temporary'); }
    }
    async function recordAttestations() {
        if (completion.state === 'completing' || locked || !loaded || switchingLanguage || attestation.state !== 'ready' || !attestation.token
            || !Array.from(root.querySelectorAll('[data-attestation-action]')).every(function (input) { return input.checked; })) return;
        invalidateCompletion();
        var payload = JSON.stringify({presentation_token: attestation.token, age_18_or_older: true, terms_accepted: true, privacy_notice_acknowledged: true});
        var sequence = attestation.sequence, node = root, epoch = contextEpoch;
        attestation.state = 'recording'; attestationControls();
        attestation.controller = new AbortController();
        function active() { return current(epoch, node) && sequence === attestation.sequence; }
        try {
            var response = await request(attestationEndpoint, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': context}, body: payload}, attestation.controller);
            if (!active()) return;
            if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
            if (response.status === 409) { retireAttestation('stale'); return; }
            var data = await response.json();
            if (!active()) return;
            if (data.code === 'policies_unavailable' || response.status === 404) { retireAttestation('unavailable'); return; }
            if (!response.ok || data.success !== true || !exactEvidence(data.evidence).recorded) throw new Error('unconfirmed');
            var time = data.evidence.recorded_at;
            // Keep the exact reviewed documents visible alongside their evidence.
            attestation.sequence++; attestation.controller = null; attestation.token = null;
            attestation.recordedAt = time; attestation.state = 'recorded';
            root.querySelectorAll('[data-attestation-action]').forEach(function (input) { input.checked = false; });
            attestationControls(); root.querySelector('[data-attestation-status]').focus();
        } catch (_error) { if (active()) retireAttestation('uncertain'); }
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
                    radio.addEventListener('change', edit, {signal: attestationListeners.signal});
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
        if (!loaded || locked || completion.receipt || completion.state === 'completing' || (busy && state === 'loading')) return;
        var input = event.target;
        if (!root.contains(input)) return;
        invalidateCompletion();
        if (input.type === 'radio' && !input.checked) return;
        model[input.dataset.draftField] = input.value;
        updatePreview();
        if (!busy && pending === null && state !== 'conflict') say(hasEdits() ? 'unsaved' : 'saved');
    }
    async function resume(replace) {
        if (busy || locked || completion.receipt || completion.intent) return;
        invalidateCompletion();
        if (replace && !window.confirm(messages().replace_confirm)) return;
        var epoch = contextEpoch, node = root;
        busy = true; say('loading');
        try {
            var response = await request(endpoint, {method: 'GET'});
            if (!current(epoch, node)) return;
            if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
            if (completion.receipt) return;
            if (!response.ok) throw new Error('unavailable');
            var data = await response.json();
            if (!current(epoch, node) || completion.receipt) return;
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
            if (attestation.state === 'loading') loadAttestations();
            if (replace && !completion.intent && !completion.receipt) { completion.state = 'idle'; readCompletionReceipt(); }
        } catch (_error) { if (current(epoch, node)) { busy = false; say(pending ? 'uncertain' : 'unavailable'); } }
    }
    function operation(section) {
        var fields = {};
        sections[section].forEach(function (key) { if (dirty(key)) fields[key] = model[key] === '' ? null : model[key]; });
        var bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
        var id = version + ':' + Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
        return JSON.stringify({expectedVersion: version, operationId: id, section: section, fields: fields});
    }
    async function save(destination, retry) {
        if (busy || locked || completion.receipt || completion.intent || !loaded || state === 'conflict' || (pending && !retry)) return;
        invalidateCompletion();
        var epoch = contextEpoch, node = root;
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
                if (!current(epoch, node)) return;
                if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
                if (completion.receipt) return;
                if (response.status === 409) { pending = null; busy = false; say('conflict'); root.querySelector('[data-save-status]').focus(); return; }
                if (response.status === 400 || response.status === 413 || response.status === 415) { pending = null; busy = false; say('invalid'); return; }
                if (!response.ok) throw new Error('unavailable');
                var data = await response.json();
                if (!current(epoch, node) || completion.receipt) return;
                if (!data.success || !data.draft || data.draft.version !== JSON.parse(payload).expectedVersion + 1) throw new Error('unavailable');
                baseline = data.draft.fields; version = data.draft.version; pending = null;
                // Do not hydrate here: edits made after dispatch stay in model
                // and on screen, and are serialized in the next necessary save.
            }
            busy = false; say('saved');
            if (destination === 'exit') { leaving = true; location.assign('/owner.php'); }
            else if (typeof destination === 'number') showStep(destination, true);
        } catch (_error) { if (current(epoch, node)) { busy = false; say(pending ? 'uncertain' : 'unavailable'); } }
    }
    async function switchLanguage(event) {
        event.preventDefault();
        if (busy || locked || completion.state === 'completing' || attestation.state === 'recording') return;
        var epoch = contextEpoch, node = root;
        switchingLanguage = true;
        retireCompletion('idle'); retireAttestation('temporary');
        busy = true; say(state);
        var nextLocale = locale === 'ar' ? 'en' : 'ar';
        try {
            var response = await request('/owner_onboarding_editor.php?lang=' + nextLocale + '&step=' + step, {method: 'GET'});
            if (!current(epoch, node)) return;
            if (response.status === 401 || response.status === 403) { stopAuthentication(); return; }
            if (!response.ok) throw new Error('unavailable');
            var page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (!current(epoch, node)) return;
            var nextRoot = page.querySelector('[data-draft-editor]');
            if (!nextRoot || nextRoot.dataset.editorContext !== context) { stopAuthentication(); return; }
            var imported = document.importNode(nextRoot, true);
            root.replaceWith(imported); root = imported;
            document.documentElement.lang = nextLocale; document.documentElement.dir = nextLocale === 'ar' ? 'rtl' : 'ltr';
            document.title = page.title; document.querySelector('.ao-skip').textContent = page.querySelector('.ao-skip').textContent;
            locale = nextLocale; switchingLanguage = false; bind(); hydrateControls(); showStep(step, false);
            root.querySelector('.hf-theme').hidden = false;
            root.querySelector('.hf-theme').setAttribute('aria-pressed', String(document.documentElement.dataset.landingTheme === 'dark'));
            busy = false; say(state); root.querySelector('[data-language-switch]').focus();
            loadAttestations();
            if (!completion.intent && !completion.receipt) { completion.state = 'idle'; readCompletionReceipt(); }
            else completionControls();
        } catch (_error) { if (current(epoch, node)) { switchingLanguage = false; busy = false; say(pending ? 'uncertain' : 'unavailable'); attestationControls(); } }
    }
    function bind() {
        retireCompletion('idle'); retireAttestation('loading');
        if (attestationListeners) attestationListeners.abort();
        attestationListeners = new AbortController();
        var listeners = {signal: attestationListeners.signal};
        root.querySelector('[data-completion-review]').addEventListener('click', prepareCompletionReview, listeners);
        root.querySelector('[data-completion-read]').addEventListener('click', readCompletionReceipt, listeners);
        root.querySelector('[data-create]').addEventListener('click', createPrivateProfile, listeners);
        root.querySelectorAll('[data-completion-confirm]').forEach(function (control) { control.addEventListener('change', completionControls, listeners); });
        root.querySelectorAll('[data-attestation-action]').forEach(function (input) { input.addEventListener('change', attestationControls, listeners); });
        root.querySelector('[data-attestation-load]').addEventListener('click', loadAttestations, listeners);
        root.querySelector('[data-attestation-record]').addEventListener('click', recordAttestations, listeners);
        root.querySelectorAll('[data-draft-field]').forEach(function (control) { control.addEventListener(control.tagName === 'SELECT' || control.type === 'radio' ? 'change' : 'input', edit, listeners); });
        root.querySelector('[data-clear-language]').addEventListener('click', function () { if (locked || completion.receipt || completion.state === 'completing') return; invalidateCompletion(); model.preferred_language = ''; hydrateControls(); if (!busy && !locked && pending === null && state !== 'conflict') say(hasEdits() ? 'unsaved' : 'saved'); }, listeners);
        root.querySelectorAll('[data-step-jump], [data-back], [data-edit-step]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                if (busy || locked || completion.state === 'completing') return;
                returning = link.hasAttribute('data-edit-step');
                showStep(returning ? Number(link.dataset.editStep) : link.hasAttribute('data-back') ? step - 1 : Number(link.dataset.stepJump), true);
            }, listeners);
        });
        root.querySelector('[data-next]').addEventListener('click', function () { var to = returning ? 5 : step + 1; returning = false; save(to, false); }, listeners);
        root.querySelectorAll('[data-save-exit]').forEach(function (button) { button.addEventListener('click', function () { save('exit', false); }, listeners); });
        root.querySelector('[data-retry]').addEventListener('click', function () { if (!loaded) resume(false); else save(null, true); }, listeners);
        root.querySelector('[data-recover]').addEventListener('click', function () { resume(true); }, listeners);
        root.querySelector('[data-fresh-context]').addEventListener('click', function (event) { if (!window.confirm(messages().fresh_confirm)) event.preventDefault(); else leaving = true; }, listeners);
        root.querySelector('[data-language-switch]').addEventListener('click', switchLanguage, listeners);
        root.querySelector('[data-preview-toggle]').addEventListener('click', function () {
            var open = root.querySelector('[data-preview-column]').classList.toggle('is-open');
            this.setAttribute('aria-expanded', String(open)); this.textContent = open ? this.dataset.closeLabel : this.dataset.openLabel;
        }, listeners);
        // On the initial document landing.js owns this button. Imported localized
        // markup needs a listener without storing any private editor state.
        if (loaded) root.querySelector('.hf-theme').addEventListener('click', function () {
            var dark = document.documentElement.dataset.landingTheme !== 'dark';
            document.documentElement.dataset.landingTheme = dark ? 'dark' : 'light'; this.setAttribute('aria-pressed', String(dark));
        }, listeners);
    }
    window.addEventListener('beforeunload', function (event) { if (!leaving && (hasEdits() || pending || busy || completion.intent || completion.state === 'completing' || attestation.state === 'recording')) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pagehide', stopAuthentication);
    window.addEventListener('pageshow', function (event) { if (event.persisted) stopAuthentication(); });
    keys.forEach(function (key) { model[key] = ''; });
    bind(); showStep(step, false); say('loading'); resume(false); readCompletionReceipt();
}());
