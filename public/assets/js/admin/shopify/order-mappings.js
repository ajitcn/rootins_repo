(function (window, document) {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
        else callback();
    }

    function parse(value) {
        try { return JSON.parse(value || '{}'); } catch (error) { return {}; }
    }

    function toast(message, error) {
        var fn = error ? window.showError : window.showSuccess;
        if (typeof fn === 'function') fn(message);
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        modal.style.display = 'none';
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    }

    function openModal(modal) {
        if (!modal) return;
        document.querySelectorAll('.modal.show').forEach(function (other) { if (other !== modal) closeModal(other); });
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('role', 'dialog');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.addEventListener('click', function () { closeModal(modal); });
        document.body.appendChild(backdrop);
    }

    function setField(form, name, value) {
        var field = form.querySelector('[name="' + name + '"]');
        if (field) field.value = value || '';
    }

    function showProduct(modal, item) {
        var title = modal.querySelector('[data-preview="title"]');
        var variant = modal.querySelector('[data-preview="variant"]');
        var ids = modal.querySelector('[data-preview="ids"]');
        var sku = modal.querySelector('[data-preview="sku"]');
        if (title) title.textContent = item.title_snapshot || 'Untitled Shopify product';
        if (variant) variant.textContent = 'Variant ' + (item.shopify_variant_id || '—');
        if (ids) ids.textContent = 'Product ' + (item.shopify_product_id || '—') + ' · Variant ' + (item.shopify_variant_id || '—');
        if (sku) sku.textContent = 'SKU ' + (item.sku || item.shopify_sku || 'Not supplied') + ' · Barcode ' + (item.barcode || 'Not supplied');
    }

    function submitForm(form, modal) {
        var medicine = form.querySelector('[name="medicine_id"]');
        var reason = form.querySelector('[name="reason"]');
        if (medicine && !medicine.value) { toast('Select an active ROOTINS medicine.', true); medicine.setAttribute('aria-invalid', 'true'); return; }
        if (reason && !reason.value.trim()) { reason.setAttribute('aria-invalid', 'true'); toast('A reason is required.', true); return; }
        var button = form.querySelector('[type="submit"]');
        var label = button ? button.innerHTML : '';
        if (button) { button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" aria-hidden="true"></span>Saving…'; }
        fetch(form.getAttribute('data-url'), { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (result) { if (!result.status) throw new Error(result.msg || 'Could not save.'); toast(result.msg || 'Saved.'); closeModal(modal); })
            .catch(function (error) { toast(error.message || 'The action could not be completed safely.', true); })
            .finally(function () { if (button) { button.disabled = false; button.innerHTML = label; } });
    }

    ready(function () {
        var mappingModal = document.getElementById('shopifyMappingModal');
        var ignoreModal = document.getElementById('shopifyIgnoreModal');
        [mappingModal, ignoreModal].forEach(function (modal) {
            if (!modal) return;
            if (modal.parentNode !== document.body) document.body.appendChild(modal);
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-hidden', 'true');
            modal.style.display = 'none';
            modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(modal); });
            modal.querySelectorAll('[data-dismiss="modal"], .close').forEach(function (button) { button.addEventListener('click', function () { closeModal(modal); }); });
        });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') document.querySelectorAll('.modal.show').forEach(closeModal); });
        document.addEventListener('click', function (event) {
            var mapButton = event.target.closest('.js-map-medicine, .js-edit-mapping');
            var ignoreButton = event.target.closest('.js-ignore-mapping');
            if (mapButton && mappingModal) { var item = parse(mapButton.getAttribute('data-item') || mapButton.getAttribute('data-mapping')); var form = mappingModal.querySelector('form'); form.reset(); showProduct(mappingModal, item); Object.keys(item).forEach(function (key) { setField(form, key, item[key]); }); setField(form, 'medicine_id', item.medicine_id); openModal(mappingModal); }
            if (ignoreButton && ignoreModal) { var ignored = parse(ignoreButton.getAttribute('data-item')); var ignoreForm = ignoreModal.querySelector('form'); ignoreForm.reset(); setField(ignoreForm, 'shopify_product_id', ignored.shopify_product_id); setField(ignoreForm, 'shopify_variant_id', ignored.shopify_variant_id); var title = ignoreModal.querySelector('[data-ignore-title]'); if (title) title.textContent = ignored.title_snapshot || 'Shopify product'; openModal(ignoreModal); }
        });
        document.addEventListener('submit', function (event) { if (event.target.id === 'shopify-mapping-form' || event.target.id === 'shopify-ignore-form') { event.preventDefault(); submitForm(event.target, event.target.closest('.modal')); } });
    });
}(window, document));
