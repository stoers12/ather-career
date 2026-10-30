(function () {
    'use strict';
    var form = document.querySelector('.owner-experience-form');
    if (!form) return;
    var current = form.querySelector('#is_current');
    var end = form.querySelector('#end_month');
    function updateCurrent(changed) {
        // Keep a rejected end month reachable after a 422, including a current/end conflict.
        if (changed && current.checked) end.value = '';
        end.disabled = current.checked && (changed || end.getAttribute('aria-invalid') !== 'true');
        end.required = !current.checked;
    }
    current.addEventListener('change', function () { updateCurrent(true); });
    window.addEventListener('pageshow', function () { updateCurrent(false); });
    updateCurrent(false);
    var invalid = form.querySelector('[aria-invalid="true"]');
    if (invalid) window.setTimeout(function () { invalid.focus(); }, 0);
}());
