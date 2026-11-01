/* Keep the server-rendered local date when device formatting is unavailable. */
(function () {
    'use strict';
    document.querySelectorAll('time[data-local-date]').forEach(function (time) {
        var date = new Date(time.dateTime);
        if (Number.isNaN(date.getTime())) return;
        time.textContent = new Intl.DateTimeFormat(document.documentElement.lang, { dateStyle: 'long', calendar: 'gregory' }).format(date);
    });
}());
