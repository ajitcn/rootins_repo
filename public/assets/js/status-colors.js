/* ROOTINS global status presentation normalizer.
 * It only adds visual classes to already-rendered labels; it does not alter
 * status values, form controls, routes, requests, or business behaviour.
 */
(function (window, document) {
    'use strict';

    var toneClasses = [
        'ri-status--green', 'ri-status--amber', 'ri-status--blue',
        'ri-status--red', 'ri-status--gray', 'ri-status--orange',
        'ri-status--purple', 'ri-status--indigo', 'ri-status--charcoal'
    ];
    var semanticClasses = ['status-success', 'status-warning', 'status-info', 'status-danger', 'status-neutral', 'status-purple'];
    var semanticForTone = { green: 'status-success', amber: 'status-warning', blue: 'status-info', indigo: 'status-info', red: 'status-danger', gray: 'status-neutral', orange: 'status-warning', purple: 'status-purple', charcoal: 'status-neutral' };

    var selectors = [
        '.badge', '.status-badge', '.source-badge', '.permission-badge',
        '[data-status]', '[data-payment-status]', '[data-delivery-status]',
        '[class*="status-badge"]', '[class*="status-item"]',
        '[class*="timeline-item"]',
        'td[data-label*="status" i]', 'td[data-label="Payment"]',
        'td[data-label="Delivery"]', 'select option'
    ].join(',');

    var normalize = function (value) {
        return String(value || '').toLowerCase().replace(/[–—]/g, '-').replace(/[_/]+/g, ' ').replace(/[^a-z0-9 -]/g, ' ').replace(/\s+/g, ' ').trim();
    };

    var shopifyTones = {
        payment: { paid: 'green', pending: 'amber', failed: 'red', refunded: 'purple', partially_refunded: 'orange', cancelled: 'red' },
        import: { processed: 'green', imported: 'blue', ready_to_import: 'blue', pending_mapping: 'amber', quarantined: 'orange', failed: 'red', received: 'gray', processing: 'blue' },
        prescription: { not_required: 'gray', uploaded: 'green', available: 'green', awaiting_review: 'amber', missing: 'red', rejected: 'red', approved: 'green' },
        delivery: { not_fulfilled: 'gray', processing: 'blue', packed: 'blue', shipped: 'indigo', delivered: 'green', fulfilled: 'green', cancelled: 'red', returned: 'purple', failed: 'red' }
    };
    var deliveryStatusMap = {
        not_fulfilled: { label: 'Not fulfilled', className: 'status-badge status-neutral' },
        fulfilled: { label: 'Fulfilled', className: 'status-badge status-success' },
        shipped: { label: 'Shipped', className: 'status-badge status-info' },
        processing: { label: 'Processing', className: 'status-badge status-info' },
        cancelled: { label: 'Cancelled', className: 'status-badge status-danger' },
        returned: { label: 'Returned', className: 'status-badge status-purple' }
    };
    var normalizeShopify = function (value) {
        var normalized = String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
        var status = normalized.replace(/[^a-z0-9_]/g, '').replace(/_+/g, '_').replace(/^_|_$/g, '');
        return status === 'order_fulfilled' ? 'fulfilled' : (status === 'partial_refund' ? 'partially_refunded' : (status === 'notfulfilled' ? 'not_fulfilled' : status));
    };
    window.rootinsShopifyStatus = function (group, value) {
        var status = normalizeShopify(value), mapped = group === 'delivery' ? deliveryStatusMap[status] : null;
        var label = mapped ? mapped.label : (status ? status.replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }) : 'Unknown');
        if (group === 'delivery' && !status) label = 'Not fulfilled';
        return { status: status, tone: (shopifyTones[group] || {})[status] || 'gray', label: label, className: mapped ? mapped.className : '' };
    };

    var toneForValue = function (value) {
        value = normalize(value);

        if (/(^| )not fulfilled( |$)/.test(value)) return 'gray';
        if (/shopify|external source|external/.test(value)) return 'purple';
        if (/self source|internal source|(^| )self( |$)|(^| )internal( |$)/.test(value)) return 'charcoal';
        if (/clarification required|clarification requested|near expiry|near-expiry|low stock|low-stock|(^| )warning( |$)/.test(value)) return 'orange';
        if (/failed|rejected|cancelled|canceled|returned|expired|no show|no-show/.test(value)) return 'red';
        if (/scheduled|in progress|dispatched|out for delivery|packed/.test(value)) return 'blue';
        if (/pending|submitted|under review|awaiting payment|processing|authorized|checked in|checked-in/.test(value)) return 'amber';
        if (/draft|inactive|unknown|not available|unavailable|not recorded|(^| )read( |$)|secure provider boundary/.test(value)) return 'gray';
        if (/(^| )active( |$)|(^| )available( |$)|approved|paid|delivered|completed|fulfilled|confirmed/.test(value)) return 'green';
        return null;
    };

    window.rootinsStatusTone = toneForValue;

    var toneFor = function (element) {
        if (element.getAttribute('data-status-group') === 'delivery' && window.rootinsShopifyStatus) {
            return window.rootinsShopifyStatus('delivery', element.getAttribute('data-status') || element.textContent).tone;
        }
        var classes = normalize(element.className && typeof element.className === 'string' ? element.className : '');
        var text = normalize(element.getAttribute('data-status') || element.getAttribute('data-payment-status') || element.getAttribute('data-delivery-status') || element.getAttribute('aria-label') || element.getAttribute('title') || element.textContent);
        return toneForValue(classes + ' ' + text);
    };

    var applyElement = function (element) {
        var tone = toneFor(element);
        if (!tone) return;
        toneClasses.forEach(function (className) { element.classList.remove(className); });
        semanticClasses.forEach(function (className) { element.classList.remove(className); });
        element.classList.add('ri-status--' + tone);
        element.classList.add(semanticForTone[tone] || 'status-neutral');
        if (element.matches('.badge, .status-badge, .source-badge, .inventory-status, [class*="status-badge"]')) {
            var hasMarkupDot = !!element.querySelector('.inventory-status-dot, .doctor-video-status-dot, [class*="status-dot"]');
            element.classList.toggle('ri-status-dot', !hasMarkupDot);
        }
    };

    var apply = function (root) {
        var elements = [];
        if (root && root.matches && root.matches(selectors)) elements.push(root);
        if (root && root.querySelectorAll) elements = elements.concat(Array.prototype.slice.call(root.querySelectorAll(selectors)));
        elements.forEach(function (element) {
            if (element.tagName === 'TD') {
                var innerStatus = element.querySelector('.badge, .status-badge, .source-badge, [data-status], [data-payment-status], [data-delivery-status], [class*="status-badge"], [class*="status-item"]');
                if (innerStatus) applyElement(innerStatus);
                return;
            }
            applyElement(element);
        });
    };

    var boot = function () {
        apply(document);
        if (window.MutationObserver) {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                        if (node.nodeType === 1) apply(node);
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}(window, document));
