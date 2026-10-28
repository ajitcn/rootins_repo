(function () {
    'use strict';

    var root = document.querySelector('[data-rootins-active-prescriptions]');
    if (!root) return;

    var endpoint = root.getAttribute('data-endpoint') || '/apps/rootins/prescriptions/active';
    var status = root.querySelector('[data-prescription-status]');
    var list = root.querySelector('[data-prescription-list]');

    function message(value, danger) {
        if (!status) return;
        status.textContent = value;
        status.className = danger ? 'rootins-prescription-status text-danger' : 'rootins-prescription-status text-muted';
    }

    function diagnostic(event, details) {
        var payload = Object.assign({ event: event }, details || {});
        root.setAttribute('data-last-diagnostic', event);
        root.dispatchEvent(new CustomEvent('rootins:prescription-diagnostic', { detail: payload }));
        if (window.console && typeof window.console.debug === 'function') window.console.debug('[ROOTINS prescriptions]', payload);
    }

    function text(value) {
        return document.createTextNode(value == null ? '' : String(value));
    }

    function render(data) {
        if (!list) return;
        list.replaceChildren();
        var prescriptions = data && Array.isArray(data.prescriptions) ? data.prescriptions : [];
        if (!prescriptions.length) {
            var empty = document.createElement('p');
            empty.className = 'rootins-prescription-empty';
            empty.appendChild(text(data && data.availability_message ? data.availability_message : 'No completed active prescriptions are available.'));
            list.appendChild(empty);
            diagnostic('no_active_prescriptions', { message: data && data.availability_message ? data.availability_message : 'none' });
            return;
        }
        diagnostic('completed_prescriptions_loaded', { prescription_count: prescriptions.length });
        prescriptions.forEach(function (prescription) {
            var card = document.createElement('article');
            card.className = 'rootins-prescription-card';
            var heading = document.createElement('h3');
            heading.appendChild(text(prescription.prescription_reference || 'Prescription'));
            card.appendChild(heading);
            var expiry = document.createElement('p');
            expiry.appendChild(text('Valid until ' + (prescription.valid_until || '')));
            card.appendChild(expiry);
            var items = Array.isArray(prescription.items) ? prescription.items : [];
            var listItems = document.createElement('ul');
            items.forEach(function (item) {
                var line = document.createElement('li');
                line.appendChild(text((item.medicine_name || item.medicine_reference || 'Medicine') + ' x ' + (item.quantity || 0)));
                listItems.appendChild(line);
            });
            card.appendChild(listItems);
            var excluded = Array.isArray(prescription.excluded_items) ? prescription.excluded_items : [];
            if (excluded.length) {
                var note = document.createElement('p');
                note.className = 'text-warning';
                note.appendChild(text(excluded.length + ' medicine(s) cannot be ordered because they are not mapped, active, or have a valid Shopify variant.'));
                card.appendChild(note);
                diagnostic('prescription_items_excluded', { excluded_count: excluded.length });
            }
            if (items.length) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'button rootins-add-prescription';
                button.setAttribute('data-prescription', JSON.stringify(prescription));
                button.appendChild(text('Add mapped medicines to cart'));
                card.appendChild(button);
            }
            list.appendChild(card);
        });
    }

    function cartVariantId(value) {
        var raw = String(value == null ? '' : value).trim();
        var match = raw.match(/^(?:gid:\/\/shopify\/ProductVariant\/)?(\d+)$/i);
        return match ? match[1] : '';
    }

    function cartJson(url, options) {
        return fetch(url, Object.assign({ headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }, options || {}))
            .then(function (response) { if (!response.ok) throw new Error('Shopify cart request failed.'); return response.json(); });
    }

    function addPrescription(prescription, button) {
        button.disabled = true;
        message('Checking your cart...');
        return cartJson('/cart.js', { method: 'GET' })
            .then(function (cart) {
                var existing = {};
                (cart.items || []).forEach(function (item) { existing[cartVariantId(item.variant_id || item.id)] = true; });
                var pending = {};
                var pendingDetails = {};
                (prescription.items || []).forEach(function (item) {
                    var variantId = cartVariantId(item.variant_id || item.shopify_variant_id);
                    var quantity = Number(item.quantity);
                    if (!variantId || existing[variantId] || quantity <= 0) return;
                    pending[variantId] = (pending[variantId] || 0) + quantity;
                    pendingDetails[variantId] = item;
                });
                var items = Object.keys(pending).map(function (variantId) {
                    var item = pendingDetails[variantId];
                    return { id: Number(variantId), quantity: pending[variantId], properties: {
                        'ROOTINS prescription': prescription.prescription_reference || '',
                        'ROOTINS medicine': item.medicine_reference || item.medicine_name || '',
                        'ROOTINS dosage': item.dosage || '',
                        'ROOTINS frequency': item.frequency || '',
                        'ROOTINS duration': item.duration || ''
                    } };
                });
                if (!items.length) {
                    message('All mapped medicines from this prescription are already in your cart.');
                    diagnostic('cart_duplicates_skipped', { prescription_reference: prescription.prescription_reference || '' });
                    return null;
                }
                diagnostic('cart_add_started', { item_count: items.length });
                return cartJson('/cart/add.js', { method: 'POST', body: JSON.stringify({ items: items }) });
            })
            .then(function (result) { if (result) { message('Mapped medicines were added. Review your cart before payment.'); diagnostic('cart_add_completed', { prescription_reference: prescription.prescription_reference || '' }); } })
            .catch(function () { message('The prescription could not be added to your cart. Please try again.', true); diagnostic('cart_add_failed'); })
            .finally(function () { button.disabled = false; });
    }

    fetch(endpoint, { headers: { 'Accept': 'application/json' } })
        .then(function (response) { return response.json().then(function (body) { if (!response.ok || !body.status) { var error = new Error(body.code || 'Prescription request failed.'); error.code = body.code || ''; throw error; } return body; }); })
        .then(function (response) { render(response.data || {}); })
        .catch(function (error) {
            var messages = {
                SHOPIFY_CUSTOMER_UNLINKED: 'Sign in and complete ROOTINS patient registration before viewing prescriptions.',
                SHOPIFY_STORE_MISMATCH: 'This Shopify customer is linked to a different store.',
                SHOPIFY_CUSTOMER_LOGIN_REQUIRED: 'Sign in to Shopify to view your ROOTINS prescriptions.'
            };
            message(messages[error.code] || 'Completed prescriptions could not be loaded. Please refresh and try again.', true);
            diagnostic('prescription_load_failed', { code: error.code || 'REQUEST_FAILED' });
        });

    root.addEventListener('click', function (event) {
        var button = event.target.closest('.rootins-add-prescription');
        if (!button || !root.contains(button)) return;
        addPrescription(JSON.parse(button.getAttribute('data-prescription') || '{}'), button);
    });
}());
