(function (window, document) {
    'use strict';

    function tone(title) {
        var value = String(title || '').toLowerCase();
        if (/converted|completed|conversion/.test(value)) return 'success';
        if (/rescheduled|scheduled|pending|status changed/.test(value)) return 'pending';
        if (/cancelled|canceled|failed|missed|error/.test(value)) return 'danger';
        return 'neutral';
    }

    function icon(title) {
        var value = String(title || '').toLowerCase();
        if (/converted|conversion/.test(value)) return 'bi-check2-circle';
        if (/completed/.test(value)) return 'bi-check-circle';
        if (/rescheduled|scheduled/.test(value)) return 'bi-calendar2-event';
        if (/cancelled|canceled|failed|missed/.test(value)) return 'bi-x-circle';
        if (/comment|remark/.test(value)) return 'bi-chat-left-text';
        if (/status/.test(value)) return 'bi-arrow-left-right';
        return 'bi-pencil-square';
    }

    function time12(value) {
        var match = String(value || '').match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
        if (!match) return value;
        var hour = parseInt(match[1], 10);
        var suffix = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;
        return String(hour).padStart(2, '0') + ':' + match[2] + ' ' + suffix;
    }

    function enhance() {
        document.querySelectorAll('.crm-timeline-item').forEach(function (item) {
            var heading = item.querySelector('.crm-timeline-card h3');
            var title = heading ? heading.textContent : '';
            item.classList.add('crm-event-tone-' + tone(title));
            var marker = item.querySelector('.crm-timeline-marker i');
            if (marker) marker.className = 'bi ' + icon(title);
        });
        document.querySelectorAll('.crm-timeline time').forEach(function (node) {
            node.textContent = node.textContent.replace(/(\d{1,2}:\d{2})(?::\d{2})?$/, function (_, value) {
                return time12(value);
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance);
    else enhance();
}(window, document));
