(function () {
    const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    function parseDate(value) {
        if (!value) {
            return null;
        }

        const parts = value.split('-').map(Number);
        if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
            return null;
        }

        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function sameDay(a, b) {
        return a && b
            && a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    function closeAll(except) {
        document.querySelectorAll('[data-date-calendar]').forEach(function (calendar) {
            if (calendar !== except) {
                calendar.hidden = true;
            }
        });
    }

    function renderCalendar(calendar, input, viewDate) {
        const selected = parseDate(input.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();
        const first = new Date(year, month, 1);
        const start = new Date(year, month, 1 - first.getDay());

        let html = '';
        html += '<div class="date-calendar__header">';
        html += '<button type="button" class="date-calendar__nav" data-cal-prev aria-label="Previous month">‹</button>';
        html += '<div class="date-calendar__title">' + monthNames[month] + ' ' + year + '</div>';
        html += '<button type="button" class="date-calendar__nav" data-cal-next aria-label="Next month">›</button>';
        html += '</div>';
        html += '<div class="date-calendar__weekdays">';
        weekdays.forEach(function (day) {
            html += '<span>' + day + '</span>';
        });
        html += '</div><div class="date-calendar__grid">';

        for (let i = 0; i < 42; i += 1) {
            const cell = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            const classes = ['date-calendar__day'];
            if (cell.getMonth() !== month) {
                classes.push('is-muted');
            }
            if (sameDay(cell, selected)) {
                classes.push('is-selected');
            }
            if (sameDay(cell, today)) {
                classes.push('is-today');
            }
            html += '<button type="button" class="' + classes.join(' ') + '" data-cal-day="' + formatDate(cell) + '">' + cell.getDate() + '</button>';
        }

        html += '</div><div class="date-calendar__footer">';
        html += '<button type="button" data-cal-clear>Clear</button>';
        html += '<button type="button" data-cal-today>Today</button>';
        html += '</div>';

        calendar.innerHTML = html;
        calendar._viewDate = viewDate;
    }

    function bindField(field) {
        const input = field.querySelector('input[type="date"]');
        const trigger = field.querySelector('[data-date-trigger]');
        const calendar = field.querySelector('[data-date-calendar]');

        if (!input || !trigger || !calendar) {
            return;
        }

        function open() {
            const selected = parseDate(input.value) || new Date();
            renderCalendar(calendar, input, new Date(selected.getFullYear(), selected.getMonth(), 1));
            closeAll(calendar);
            calendar.hidden = false;
        }

        function close() {
            calendar.hidden = true;
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            if (calendar.hidden) {
                open();
            } else {
                close();
            }
        });

        calendar.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.matches('[data-cal-prev]')) {
                const current = calendar._viewDate || new Date();
                renderCalendar(calendar, input, new Date(current.getFullYear(), current.getMonth() - 1, 1));
                return;
            }

            if (target.matches('[data-cal-next]')) {
                const current = calendar._viewDate || new Date();
                renderCalendar(calendar, input, new Date(current.getFullYear(), current.getMonth() + 1, 1));
                return;
            }

            if (target.matches('[data-cal-clear]')) {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close();
                return;
            }

            if (target.matches('[data-cal-today]')) {
                input.value = formatDate(new Date());
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close();
                return;
            }

            const day = target.getAttribute('data-cal-day');
            if (day) {
                input.value = day;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close();
            }
        });
    }

    document.querySelectorAll('.date-field').forEach(bindField);

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof Node)) {
            return;
        }

        if (!target.closest || !target.closest('.date-field')) {
            closeAll(null);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });
})();
