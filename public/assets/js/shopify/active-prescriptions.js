(function () {
    'use strict';

    var root = document.querySelector('[data-rootins-active-prescriptions]');
    if (!root) return;

    var syncing = {};
    var endpoint = root.getAttribute('data-endpoint') || '/apps/rootins/prescriptions/active';
    var status = root.querySelector('[data-prescription-status]');
    var list = root.querySelector('[data-prescription-list]');

    function text(value) { return document.createTextNode(value == null ? '' : String(value)); }
    function message(value, danger) { if (!status) return; status.textContent = value; status.className = danger ? 'rootins-prescription-status text-danger' : 'rootins-prescription-status text-muted'; }
    function diagnostic(event, details) { var payload = Object.assign({ event: event }, details || {}); root.setAttribute('data-last-diagnostic', event); root.dispatchEvent(new CustomEvent('rootins:prescription-diagnostic', { detail: payload })); if (window.console && console.debug) console.debug('[ROOTINS prescriptions]', payload); }
    function cartVariantId(value) { var match = String(value == null ? '' : value).trim().match(/^(?:gid:\/\/shopify\/ProductVariant\/)?(\d+)$/i); return match ? match[1] : ''; }
    function cartLineReference(item) { var properties = item && item.properties ? item.properties : {}; return String(properties._rx_reference || '').trim(); }
    function cartLineKey(reference, variantId) { return String(reference || '').trim() + '|' + String(variantId || '').trim(); }
    function cartJson(url, options) { return fetch(url, Object.assign({ headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }, options || {})).then(function (response) { if (!response.ok) throw new Error('Shopify cart request failed.'); return response.json(); }); }
    function uuid() { if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID(); return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }); }
    function updateCartAttributes(cart, prescription) { var attributes = Object.assign({}, cart.attributes || {}), references = String(attributes.rootins_prescription_references || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean), reference = String(prescription.reference || '').trim(); if (reference && references.indexOf(reference) === -1) references.push(reference); if (!references.length) return Promise.resolve(cart); attributes.rootins_prescription_references = references.join(','); attributes.rootins_prescription_reference = reference; return cartJson('/cart/update.js', { method: 'POST', body: JSON.stringify({ attributes: attributes }) }).catch(function () { return cart; }); }
    function acknowledgeCartPush(prescription, items, cartStatus, reason) { var ackEndpoint = root.getAttribute('data-ack-endpoint') || '/apps/rootins/prescriptions/cart-status'; return fetch(ackEndpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Idempotency-Key': uuid() }, body: JSON.stringify({ reference: prescription.reference || '', cart_status: cartStatus || 'pushed', items: items || [], failure_reason: reason || '' }) }).then(function (response) { return response.json().then(function (body) { if (!response.ok || !body.status) throw new Error(body.code || 'Cart push acknowledgement failed.'); return body; }); }); }
    function openCartDrawer() { var drawer = document.querySelector('cart-drawer'); if (drawer && typeof drawer.open === 'function') drawer.open(); document.dispatchEvent(new CustomEvent('cart:refresh')); }
    function markPushed(button, prescription) { prescription.cart_status = 'cart_pushed'; prescription.cart_sync_status = 'synced'; if (button) { button.disabled = false; button.textContent = 'Pushed to cart'; } message('Pushed to cart. Review your cart before payment.'); diagnostic('cart_push_recorded', { prescription_reference: prescription.reference || '' }); }
    function render(data) {
        if (!list) return;
        list.replaceChildren();
        var prescriptions = data && Array.isArray(data.prescriptions) ? data.prescriptions : [];
        if (!prescriptions.length) { var empty = document.createElement('p'); empty.className = 'rootins-prescription-empty'; empty.appendChild(text(data && data.availability_message ? data.availability_message : 'No completed active prescriptions are available.')); list.appendChild(empty); diagnostic('no_active_prescriptions'); return; }
        diagnostic('completed_prescriptions_loaded', { customer_id: data && data.customer ? data.customer.shopify_customer_id || '' : '', total_prescriptions: prescriptions.length });
        prescriptions.forEach(function (prescription) {
            var card = document.createElement('article'); card.className = 'rootins-prescription-card';
            var heading = document.createElement('h3'); heading.appendChild(text('Doctor ne ye dawai likhi hai - ' + (prescription.reference || 'Prescription'))); card.appendChild(heading);
            var expiry = document.createElement('p'); expiry.appendChild(text('Valid until ' + (prescription.valid_until || ''))); card.appendChild(expiry);
            var items = Array.isArray(prescription.items) ? prescription.items : [], itemList = document.createElement('ul');
            items.forEach(function (item) { var line = document.createElement('li'); line.appendChild(text((item.medicine_name || 'Medicine') + ' x ' + (item.quantity || 0))); itemList.appendChild(line); }); card.appendChild(itemList);
            var excluded = Array.isArray(data.excluded_items) ? data.excluded_items.filter(function (item) { return item.reference === prescription.reference; }) : [];
            if (excluded.length) { var note = document.createElement('p'); note.className = 'text-warning'; note.appendChild(text(excluded.length + ' medicine(s) cannot be ordered: ' + excluded.map(function (item) { return item.reason || 'mapping required'; }).join(', ') + '.')); card.appendChild(note); diagnostic('prescription_items_excluded', { prescription_reference: prescription.reference || '', skipped_item_reason: excluded.map(function (item) { return item.reason || 'unknown'; }) }); }
            if (items.length && prescription.cart_status === 'available') { var button = document.createElement('button'); button.type = 'button'; button.className = 'button rootins-add-prescription'; button.setAttribute('data-prescription', JSON.stringify(prescription)); button.setAttribute('data-prescription-reference', prescription.reference || ''); button.appendChild(text(prescription.cart_sync_status === 'sync_failed' ? 'Retry cart sync' : 'Add mapped medicines to cart')); card.appendChild(button); }
            list.appendChild(card);
            diagnostic('prescription_evaluated', { prescription_reference: prescription.reference || '', cart_status: prescription.cart_status || '', eligible_item_count: items.length, skipped_item_reason: ['ordered', 'paid'].indexOf(prescription.cart_status) !== -1 ? 'already_ordered' : (['expired', 'cancelled'].indexOf(prescription.cart_status) !== -1 ? 'inactive' : '') });
        });
    }
    function removeInactiveLines(cart, data) { var inactive = {}, updates = {}, removed = 0; (data.prescriptions || []).forEach(function (prescription) { if (['cancelled', 'expired'].indexOf(prescription.cart_status) === -1) return; var reference = String(prescription.reference || '').trim(); if (reference) inactive[reference] = true; }); (cart.items || []).forEach(function (item) { if (inactive[cartLineReference(item)] && item.key) { updates[item.key] = 0; removed += 1; } }); diagnostic('inactive_prescription_lines_checked', { removed_item_count: removed }); if (!Object.keys(updates).length) return Promise.resolve(cart); return cartJson('/cart/update.js', { method: 'POST', body: JSON.stringify({ updates: updates }) }).catch(function () { return cart; }); }
    function addPrescription(prescription, button) {
        var reference = String(prescription.reference || '').trim();
        if (!reference || syncing[reference]) { diagnostic('prescription_sync_skipped', { prescription_reference: reference, skipped_item_reason: 'already_syncing_or_missing_reference' }); return Promise.resolve(); }
        syncing[reference] = true; if (button) button.disabled = true; message('Checking your cart...');
        return cartJson('/cart.js', { method: 'GET' }).then(function (cart) {
            var existing = {}, pending = {}, details = {}, ackItems = {}, eligibleCount = 0, skippedCount = 0;
            (cart.items || []).forEach(function (item) { var variantId = cartVariantId(item.variant_id || item.id), lineReference = cartLineReference(item); if (variantId && lineReference) existing[cartLineKey(lineReference, variantId)] = true; });
            (prescription.items || []).forEach(function (item) { var variantId = cartVariantId(item.variant_id), quantity = Number(item.quantity), remaining = Number(item.quantity_remaining == null ? quantity : item.quantity_remaining), key = cartLineKey(reference, variantId); if (!variantId || quantity <= 0 || remaining <= 0) { skippedCount += 1; diagnostic('prescription_item_skipped', { prescription_reference: reference, variant_id: variantId, skipped_item_reason: !variantId ? 'variant_invalid' : 'quantity_unavailable' }); return; } eligibleCount += 1; var amount = Math.min(quantity, remaining); ackItems[variantId] = (ackItems[variantId] || 0) + amount; if (existing[key]) { skippedCount += 1; diagnostic('prescription_item_skipped', { prescription_reference: reference, variant_id: variantId, skipped_item_reason: 'already_in_cart' }); return; } pending[variantId] = (pending[variantId] || 0) + amount; details[variantId] = item; });
            var items = Object.keys(pending).map(function (variantId) { var item = details[variantId]; return { id: Number(variantId), quantity: pending[variantId], properties: { '_rx_reference': reference, 'ROOTINS medicine': item.medicine_name || '', 'ROOTINS dosage': item.dosage || '', 'ROOTINS frequency': item.frequency || '', 'ROOTINS duration': item.duration || '' } }; });
            var acknowledgeItems = Object.keys(ackItems).map(function (variantId) { return { variant_id: variantId, quantity: ackItems[variantId] }; });
            diagnostic('prescription_sync_evaluated', { prescription_reference: reference, cart_status: prescription.cart_status || '', eligible_item_count: eligibleCount, skipped_item_count: skippedCount, added_item_count: items.length });
            var afterAttributes = updateCartAttributes(cart, prescription);
            if (!items.length) { diagnostic('cart_duplicates_skipped', { prescription_reference: reference }); return afterAttributes.then(function () { return acknowledgeCartPush(prescription, acknowledgeItems); }).then(function () { markPushed(button, prescription); openCartDrawer(); }); }
            diagnostic('cart_add_started', { prescription_reference: reference, item_count: items.length });
            return afterAttributes.then(function () { return cartJson('/cart/add.js', { method: 'POST', body: JSON.stringify({ items: items }) }); }).then(function () { diagnostic('cart_items_added', { prescription_reference: reference, added_item_count: items.length }); return acknowledgeCartPush(prescription, acknowledgeItems).then(function () { markPushed(button, prescription); openCartDrawer(); }); });
        }).catch(function (error) { return acknowledgeCartPush(prescription, [], 'failed', error && error.message).catch(function () {}).then(function () { message('The prescription could not be added to your cart. Please try again.', true); diagnostic('cart_add_failed', { prescription_reference: reference, skipped_item_reason: error && error.message }); }); }).finally(function () { delete syncing[reference]; if (button) button.disabled = false; });
    }
    function load() {
        return Promise.all([fetch(endpoint, { headers: { 'Accept': 'application/json' } }), fetch('/cart.js', { headers: { 'Accept': 'application/json' } })]).then(function (responses) { return Promise.all(responses.map(function (response, index) { return response.json().then(function (body) { if (!response.ok || (index === 0 && !body.status)) { var error = new Error(body.code || 'Prescription request failed.'); error.code = body.code || ''; throw error; } return body; }); })); }).then(function (responses) {
            var data = responses[0].data || {}, cart = responses[1];
            diagnostic('prescription_collection_evaluated', { customer_id: data.customer ? data.customer.shopify_customer_id || '' : '', total_prescriptions: Array.isArray(data.prescriptions) ? data.prescriptions.length : 0 });
            render(data);
            return removeInactiveLines(cart, data).then(function () { (data.prescriptions || []).forEach(function (prescription) { if (prescription.cart_status !== 'available' || !prescription.items || !prescription.items.length || !list) return; var button = Array.prototype.slice.call(list.querySelectorAll('.rootins-add-prescription')).find(function (candidate) { return candidate.getAttribute('data-prescription-reference') === prescription.reference; }); addPrescription(prescription, button || null); }); });
        });
    }
    load().catch(function (error) { var messages = { SHOPIFY_CUSTOMER_UNLINKED: 'Sign in and complete ROOTINS patient registration before viewing prescriptions.', SHOPIFY_STORE_MISMATCH: 'This Shopify customer is linked to a different store.', SHOPIFY_CUSTOMER_LOGIN_REQUIRED: 'Sign in to Shopify to view your ROOTINS prescriptions.' }; message(messages[error.code] || 'Completed prescriptions could not be loaded. Please refresh and try again.', true); diagnostic('prescription_load_failed', { skipped_item_reason: error.code || 'REQUEST_FAILED' }); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) load().catch(function () {}); });
    root.addEventListener('click', function (event) { var button = event.target.closest('.rootins-add-prescription'); if (!button || !root.contains(button)) return; addPrescription(JSON.parse(button.getAttribute('data-prescription') || '{}'), button); });
}());
