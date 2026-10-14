(function () {
    'use strict';

    function addLeadTooltips() {
        document.querySelectorAll('.crm-lead-primary, .crm-leads-page .crm-table tbody td:first-child small').forEach(function (node) {
            var value = (node.textContent || '').trim();
            if (value && !node.getAttribute('title')) {
                node.setAttribute('title', value);
            }
        });
        document.querySelectorAll('.crm-leads-page td[data-label="Next follow-up"] small').forEach(function (node) {
            var match = (node.textContent || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
            if (match) {
                var hour = parseInt(match[1], 10);
                var suffix = hour >= 12 ? 'PM' : 'AM';
                hour = hour % 12 || 12;
                node.textContent = String(hour).padStart(2, '0') + ':' + match[2] + ' ' + suffix;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addLeadTooltips);
    } else {
        addLeadTooltips();
    }
}());
