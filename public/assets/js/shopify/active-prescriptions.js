(function () {
    'use strict';

    var root = document.querySelector('[data-rootins-active-prescriptions]');
    if (!root) return;

    var endpoint = root.getAttribute('data-endpoint') || '/apps/rootins/prescriptions/active';
    var status = root.querySelector('[data-prescription-status]');
    var list = root.querySelector('[data-prescription-list]');

    function message(text, danger) {
        if (!status) return;
        status.textContent = text;
        status.className = danger ? 'rootins-prescription-status text-danger' : 'rootins-prescription-status text-muted';
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
            empty.appendChild(text('No completed active prescriptions are available.'));
            list.appendChild(empty);
            return;
        }
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
                line.appendChild(text((item.medicine_name || item.medicine_reference || 'Medicine') + ' × ' + (item.quantity || 0)));
                listItems.appendChild(line);
            });
            card.appendChild(listItems);
            var excluded = Array.isArray(prescription.excluded_items) ? prescription.excluded_items : [];
            if (excluded.length) {
                var note = document.createElement('p');
                note.className = 'text-warning';
                note.appendChild(text(excluded.length + ' medicine(s) cannot be ordered because they are not mapped or active.'));
                card.appendChild(note);
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

    function cartJson(url, options) {
        return fetch(url, Object.assign({ headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }, options || {}))
            .then(function (response) { if (!response.ok) throw new Error('Shopify cart request failed.'); return response.json(); });
    }

    function addPrescription(prescription, button) {
        button.disabled = true;
        message('Checking your cart…');
        return cartJson('/cart.js', { method: 'GET' })
            .then(function (cart) {
                var existing = {};
                (cart.items || []).forEach(function (item) { existing[String(item.variant_id || item.id)] = true; });
                var items = (prescription.items || []).filter(function (item) {
                    return item.shopify_variant_id && !existing[String(item.shopify_variant_id)] && Number(item.quantity) > 0;
                }).map(function (item) {
                    return { id: Number(item.shopify_variant_id), quantity: Number(item.quantity), properties: {
                        'ROOTINS prescription': prescription.prescription_reference || '',
                        'ROOTINS medicine': item.medicine_reference || item.medicine_name || '',
                        'ROOTINS dosage': item.dosage || '',
                        'ROOTINS frequency': item.frequency || '',
                        'ROOTINS duration': item.duration || ''
                    } };
                });
                if (!items.length) {
                    message('All mapped medicines from this prescription are already in your cart.');
                    return null;
                }
                return cartJson('/cart/add.js', { method: 'POST', body: JSON.stringify({ items: items }) });
            })
            .then(function (result) { if (result) message('Mapped medicines were added. Review your cart before payment.'); })
            .catch(function () { message('The prescription could not be added to your cart. Please try again.', true); })
            .finally(function () { button.disabled = false; });
    }

    fetch(endpoint, { headers: { 'Accept': 'application/json' } })
        .then(function (response) { if (!response.ok) throw new Error('Prescription request failed.'); return response.json(); })
        .then(function (response) { if (!response.status) throw new Error('Prescription request failed.'); render(response.data || {}); })
        .catch(function () { message('Completed prescriptions could not be loaded. Please refresh and try again.', true); });

    root.addEventListener('click', function (event) {
        var button = event.target.closest('.rootins-add-prescription');
        if (!button || !root.contains(button)) return;
        addPrescription(JSON.parse(button.getAttribute('data-prescription') || '{}'), button);
    });
}());
