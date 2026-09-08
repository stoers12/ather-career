(function () {
    'use strict';
    var modal;
    var lastActiveElement;
    function confirmAction(message, callback, title, actionLabel) {
        if (!modal) {
            modal = document.createElement('div');
            modal.className = 'confirm-modal';
            modal.innerHTML = '<div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message"><h2 id="confirm-title">Are you sure?</h2><p class="confirm-message" id="confirm-message"></p><div class="confirm-actions"><button type="button" class="button-link confirm-cancel">Cancel</button><button type="button" class="button-danger confirm-ok">Continue</button></div></div>';
            document.body.appendChild(modal);
        }
        lastActiveElement = document.activeElement;
        modal.querySelector('#confirm-title').textContent = title || 'Are you sure?';
        modal.querySelector('.confirm-message').textContent = message;
        modal.querySelector('.confirm-ok').textContent = actionLabel || 'Continue';
        modal.hidden = false;
        var cancel = modal.querySelector('.confirm-cancel');
        var ok = modal.querySelector('.confirm-ok');
        var close = function () {
            modal.hidden = true;
            if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
                lastActiveElement.focus();
            }
        };
        cancel.onclick = close;
        ok.onclick = function () { close(); callback(); };
        modal.onkeydown = function (event) {
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            if (event.key === 'Tab') {
                var focusable = modal.querySelectorAll('button:not([disabled])');
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };
        modal.onclick = function (event) {
            if (event.target === modal) { close(); }
        };
        ok.focus();
    }
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') { form.dataset.confirmed = ''; return; }
            event.preventDefault();
            confirmAction(form.getAttribute('data-confirm'), function () {
                form.dataset.confirmed = '1';
                form.requestSubmit();
            }, form.getAttribute('data-confirm-title'), form.getAttribute('data-confirm-action'));
        });
    });
    document.querySelectorAll('[data-confirm]:not(form):not(button)').forEach(function (element) {
        element.addEventListener('click', function (event) {
            event.preventDefault();
            confirmAction(element.getAttribute('data-confirm'), function () { window.location.href = element.href; }, element.getAttribute('data-confirm-title'), element.getAttribute('data-confirm-action'));
        });
    });
    document.querySelectorAll('input[name="remove_image"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.checked) return;
            input.checked = false;
            var titleField = input.form ? input.form.querySelector('input[name="title"]') : null;
            var projectTitle = titleField && titleField.value.trim();
            var message = projectTitle ? 'Remove the current image for ' + projectTitle + ' when this project is saved?' : 'Remove the current project image when this project is saved?';
            confirmAction(message, function () {
                input.checked = true;
                input.focus();
            }, 'Remove project image?', 'Remove image');
        });
    });

    function restoreOwnerFormState(form) {
        form.dataset.submitting = '';
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-owner-pending-original]').forEach(function (control) {
            control.disabled = false;
            control.removeAttribute('aria-disabled');
            control.textContent = control.dataset.ownerPendingOriginal;
            control.style.minWidth = control.dataset.ownerPendingWidth || '';
            delete control.dataset.ownerPendingOriginal;
            delete control.dataset.ownerPendingWidth;
        });
    }

    document.querySelectorAll('form[data-owner-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

            var submitter = event.submitter || form.querySelector('button[type="submit"]:not([disabled]), input[type="submit"]:not([disabled])');
            if (!submitter) return;

            form.dataset.submitting = '1';
            form.setAttribute('aria-busy', 'true');
            submitter.dataset.ownerPendingOriginal = submitter.textContent;
            submitter.dataset.ownerPendingWidth = submitter.style.minWidth || '';
            submitter.style.minWidth = Math.ceil(submitter.getBoundingClientRect().width) + 'px';
            submitter.textContent = submitter.getAttribute('data-pending-label') || 'Saving…';
            submitter.disabled = true;
            submitter.setAttribute('aria-disabled', 'true');
        });
    });

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-owner-form]').forEach(restoreOwnerFormState);
    });

    var errorSummary = document.querySelector('[data-error-summary]');
    if (errorSummary) {
        window.setTimeout(function () { errorSummary.focus(); }, 0);
    }

    function formState(form) {
        return JSON.stringify(Array.from(new FormData(form).entries(), function (entry) {
            var value = entry[1];
            return [entry[0], value instanceof File ? [value.name, value.size, value.lastModified] : value];
        }));
    }

    var profileForm = document.getElementById('profile-form');
    if (profileForm) {
        var initialState = formState(profileForm);
        function hasChanges() {
            return formState(profileForm) !== initialState;
        }
        profileForm.addEventListener('submit', function () { initialState = formState(profileForm); });
        window.addEventListener('beforeunload', function (event) {
            if (hasChanges()) { event.preventDefault(); event.returnValue = ''; }
        });
    }

    var profileImageInput = document.querySelector('[data-profile-image-input]');
    if (profileImageInput) {
        var profileImageStatus = document.getElementById('profile-image-status');
        var profileImageMaxBytes = Number(profileImageInput.dataset.profileImageMaxBytes);
        profileImageInput.addEventListener('change', function () {
            var file = profileImageInput.files && profileImageInput.files[0];
            if (!profileImageStatus) return;
            if (!file) {
                profileImageStatus.textContent = '';
                return;
            }
            if (Number.isFinite(profileImageMaxBytes) && file.size > profileImageMaxBytes) {
                profileImageInput.value = '';
                profileImageStatus.textContent = 'Choose a profile photo no larger than ' + Math.round(profileImageMaxBytes / (1024 * 1024)) + ' MB.';
                return;
            }
            profileImageStatus.textContent = 'Selected: ' + file.name + '. Validation continues when you save.';
        });
    }

    var projectImageInput = document.querySelector('[data-project-image-input]');
    if (projectImageInput) {
        var projectImageStatus = document.getElementById('project-image-status');
        var projectImageMaxBytes = Number(projectImageInput.dataset.projectImageMaxBytes);
        projectImageInput.addEventListener('change', function () {
            var file = projectImageInput.files && projectImageInput.files[0];
            if (!projectImageStatus) return;
            if (!file) {
                projectImageStatus.textContent = '';
                return;
            }
            if (Number.isFinite(projectImageMaxBytes) && file.size > projectImageMaxBytes) {
                projectImageInput.value = '';
                projectImageStatus.textContent = 'Choose a project image no larger than ' + Math.round(projectImageMaxBytes / (1024 * 1024)) + ' MB.';
                return;
            }
            projectImageStatus.textContent = 'Selected: ' + file.name + '. Validation continues when you save.';
        });
    }

    document.querySelectorAll('[data-copy-public-url]').forEach(function (button) {
        var publicUrl = document.getElementById(button.getAttribute('data-copy-public-url'));
        var feedback = document.getElementById('publication-copy-feedback');
        if (!publicUrl || !feedback || !navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            return;
        }
        button.hidden = false;
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(publicUrl.textContent.trim()).then(function () {
                feedback.textContent = 'Portfolio link copied.';
                feedback.classList.remove('error');
            }).catch(function () {
                feedback.textContent = 'Copying the Portfolio link failed. Select and copy the link manually.';
                feedback.classList.add('error');
            });
        });
    });

    var projectSearch = document.querySelector('[data-project-search]');
    if (projectSearch) {
        projectSearch.addEventListener('input', function () {
            var query = projectSearch.value.trim().toLowerCase();
            document.querySelectorAll('[data-project-card]').forEach(function (card) {
                card.hidden = query !== '' && card.getAttribute('data-search').indexOf(query) === -1;
            });
        });
    }
}());
