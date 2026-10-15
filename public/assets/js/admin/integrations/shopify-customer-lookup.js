(function () {
    'use strict';

    const form = document.getElementById('shopify-customer-lookup-form');
    if (!form) return;

    const input = document.getElementById('shopify-customer-lookup-id');
    const submit = document.getElementById('shopify-customer-lookup-submit');
    const modal = $('#shopify-customer-lookup-modal');
    const result = document.getElementById('shopify-customer-lookup-result');
    const copy = document.getElementById('shopify-customer-lookup-copy');
    let activeController = null;
    let timeoutId = null;

    const escapeHtml = function (value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character];
        });
    };

    const showMessage = function (message, className) {
        result.innerHTML = '<div class="alert ' + className + ' mb-0" role="status">' + escapeHtml(message) + '</div>';
    };

    const showCustomer = function (customer) {
        const rows = [
            ['Customer ID', customer.shopify_customer_id],
            ['First name', customer.first_name],
            ['Last name', customer.last_name],
            ['Email', customer.email],
            ['Mobile', customer.mobile],
            ['State', customer.state],
            ['Created', customer.created_at],
            ['Updated', customer.updated_at],
            ['Store domain', customer.store_domain],
            ['ROOTINS match', customer.match_status],
            ['Patient reference', customer.linked_patient_reference],
        ].filter(function (row) { return row[1] !== null && row[1] !== undefined && String(row[1]) !== ''; });
        result.innerHTML = '<dl class="row mb-0">' + rows.map(function (row) {
            return '<dt class="col-sm-4 text-muted small mb-2">' + escapeHtml(row[0]) + '</dt><dd class="col-sm-8 text-break mb-2">' + escapeHtml(row[1]) + '</dd>';
        }).join('') + '</dl>';
        copy.disabled = false;
        copy.dataset.customerId = customer.shopify_customer_id || '';
    };

    const reset = function () {
        copy.disabled = true;
        delete copy.dataset.customerId;
        result.innerHTML = '<div class="text-muted">Enter a customer ID to begin.</div>';
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (submit.disabled) return;
        const customerId = input.value.trim();
        if (!customerId) {
            showMessage('Enter a Shopify customer ID.', 'alert-warning');
            return;
        }

        submit.disabled = true;
        activeController = new AbortController();
        modal.modal('show');
        showMessage('Loading customer details…', 'alert-light');
        timeoutId = window.setTimeout(function () { activeController.abort(); }, 30000);

        fetch(window.rootinsShopifyCustomerLookupUrl + '/' + encodeURIComponent(customerId), {
            method: 'GET',
            credentials: 'same-origin',
            signal: activeController.signal,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .then(function (payload) {
                if (!payload.response.ok || !payload.data.status) throw new Error(payload.data.msg || 'Customer details could not be loaded.');
                if (!payload.data.data) { showMessage('No customer details were returned.', 'alert-warning'); return; }
                showCustomer(payload.data.data);
            })
            .catch(function (error) { showMessage(error.name === 'AbortError' ? 'The Shopify request timed out.' : (error.message || 'Customer details could not be loaded safely.'), 'alert-danger'); })
            .finally(function () {
                window.clearTimeout(timeoutId);
                activeController = null;
                submit.disabled = false;
            });
    });

    copy.addEventListener('click', function () {
        const customerId = copy.dataset.customerId;
        if (!customerId || !navigator.clipboard) return;
        navigator.clipboard.writeText(customerId).then(function () {
            copy.textContent = 'Copied';
            window.setTimeout(function () { copy.textContent = 'Copy Customer ID'; }, 1500);
        });
    });

    modal.on('hidden.bs.modal', reset);
}());
