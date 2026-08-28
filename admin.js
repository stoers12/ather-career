(function () {
    'use strict';
    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'toast ' + (type || 'success');
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(function () { toast.classList.add('visible'); }, 20);
        window.setTimeout(function () {
            toast.classList.remove('visible');
            window.setTimeout(function () { toast.remove(); }, 250);
        }, 3500);
    }

    var modal;
    var lastActiveElement;
    function confirmAction(message, callback, title, actionLabel) {
        if (!modal) {
            modal = document.createElement('div');
            modal.className = 'confirm-modal';
            modal.innerHTML = '<div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-title"><h2 id="confirm-title">Are you sure?</h2><p class="confirm-message"></p><div class="confirm-actions"><button type="button" class="button-link confirm-cancel">Cancel</button><button type="button" class="button-danger confirm-ok">Continue</button></div></div>';
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

    var profileForm = document.getElementById('profile-form');
    if (profileForm) {
        var initialState = new FormData(profileForm);
        function hasChanges() {
            var current = new FormData(profileForm);
            for (var pair of initialState.entries()) {
                if (current.get(pair[0]) !== pair[1]) return true;
            }
            return false;
        }
        profileForm.addEventListener('submit', function () { initialState = new FormData(profileForm); });
        window.addEventListener('beforeunload', function (event) {
            if (hasChanges()) { event.preventDefault(); event.returnValue = ''; }
        });
    }

    document.querySelectorAll('[data-image-preview]').forEach(function (input) {
        var photoSubmit = document.querySelector('[data-photo-submit]');
        var selectionStatus = document.getElementById('photo-selection-status');
        input.addEventListener('change', function () {
            var preview = document.querySelector(input.getAttribute('data-image-preview'));
            var file = input.files && input.files[0];
            var initials = document.querySelector('[data-profile-initials]');
            var resetSelection = function (message) {
                input.value = '';
                if (photoSubmit) photoSubmit.disabled = true;
                if (selectionStatus) selectionStatus.textContent = message || '';
                if (preview) {
                    if (preview.dataset.savedSrc) {
                        preview.src = preview.dataset.savedSrc;
                        preview.hidden = false;
                    } else {
                        preview.removeAttribute('src');
                        preview.hidden = true;
                    }
                }
                if (initials && !preview.dataset.savedSrc) initials.hidden = false;
            };
            if (!preview || !file) {
                resetSelection('');
                return;
            }
            if (!file.type.match(/^image\/(jpeg|png)$/) || file.size > 8 * 1024 * 1024) {
                resetSelection('');
                showToast('Choose a JPG or PNG image up to 8 MB.', 'error');
                return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                var testImage = new Image();
                testImage.onload = function () {
                    if (testImage.naturalWidth < 400 || testImage.naturalHeight < 400) {
                        resetSelection('');
                        showToast('Profile photo must be at least 400 × 400 pixels.', 'error');
                        return;
                    }
                    preview.src = reader.result;
                    preview.hidden = false;
                    if (initials) initials.hidden = true;
                    if (selectionStatus) selectionStatus.textContent = 'Selected: ' + file.name;
                    if (photoSubmit) photoSubmit.disabled = false;
                };
                testImage.onerror = function () {
                    resetSelection('');
                    showToast('The selected image could not be previewed.', 'error');
                };
                testImage.src = reader.result;
            };
            reader.onerror = function () {
                resetSelection('');
                showToast('The selected image could not be read.', 'error');
            };
            reader.readAsDataURL(file);
        });
    });

    document.querySelectorAll('[data-file-trigger]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var input = document.getElementById(trigger.getAttribute('data-file-trigger'));
            if (input) input.click();
        });
    });

    var projectForm = document.querySelector('[data-project-form]');
    var projectFormToggle = document.querySelector('[data-project-form-toggle]');
    function setProjectFormVisible(visible, shouldFocus) {
        if (!projectForm || !projectFormToggle) return;
        projectForm.hidden = !visible;
        projectFormToggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
        if (visible && shouldFocus) {
            window.setTimeout(function () {
                var firstField = projectForm.querySelector('#title');
                if (firstField) firstField.focus();
            }, 0);
        }
    }
    if (projectForm && projectFormToggle) {
        projectFormToggle.addEventListener('click', function () {
            setProjectFormVisible(projectForm.hidden, true);
        });
        var projectFormCancel = projectForm.querySelector('[data-project-form-cancel]');
        if (projectFormCancel) {
            projectFormCancel.addEventListener('click', function () {
                var form = projectForm.querySelector('form');
                if (form) form.reset();
                setProjectFormVisible(false, false);
                projectFormToggle.focus();
            });
        }
    }

    var projectImageInput = document.querySelector('[data-project-image-input]');
    if (projectImageInput) {
        var projectImageStatus = document.getElementById('project-image-status');
        projectImageInput.addEventListener('change', function () {
            var file = projectImageInput.files && projectImageInput.files[0];
            if (!file) {
                if (projectImageStatus) projectImageStatus.textContent = '';
                return;
            }
            if (!file.type.match(/^image\/(jpeg|png|webp)$/) || file.size > 2 * 1024 * 1024) {
                projectImageInput.value = '';
                if (projectImageStatus) projectImageStatus.textContent = '';
                showToast('Choose a JPG, PNG, or WEBP image up to 2 MB.', 'error');
                return;
            }
            if (projectImageStatus) projectImageStatus.textContent = 'Selected: ' + file.name;
        });
    }

    var skillInput = document.getElementById('skill_name');
    var skillNames = Array.prototype.map.call(document.querySelectorAll('[data-skill-name]'), function (item) {
        return item.getAttribute('data-skill-name').toLowerCase();
    });
    if (skillInput) {
        skillInput.addEventListener('input', function () {
            var duplicate = skillNames.indexOf(skillInput.value.trim().toLowerCase()) !== -1;
            var feedback = document.getElementById('skill-feedback');
            if (feedback) {
                feedback.textContent = duplicate ? 'That skill already exists.' : '';
                feedback.className = duplicate ? 'field-feedback error' : 'field-feedback';
            }
            skillInput.setCustomValidity(duplicate ? 'That skill already exists.' : '');
        });
    }

    document.querySelectorAll('.status-message[data-toast]').forEach(function (message) {
        showToast(message.textContent.trim(), message.classList.contains('error') ? 'error' : 'success');
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
