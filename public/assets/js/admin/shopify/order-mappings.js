(function (window, document) {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
        else callback();
    }

    function parse(value) {
        try { return JSON.parse(value || '{}'); } catch (error) { return {}; }
    }

    function toast(message, isError) {
        var fn = isError ? window.showError : window.showSuccess;
        if (typeof fn === 'function') fn(message);
        else if (isError) window.alert(message);
    }

    function csrfInput() {
        var inputs = document.querySelectorAll('input[type="hidden"][name="csrf_rtn"]');
        for (var i = 0; i < inputs.length; i++) if (inputs[i].value) return inputs[i];
        return inputs[0] || null;
    }

    function ensureCsrfFields() {
        var nameMeta = document.querySelector('meta[name="csrf-token-name"]');
        var hashMeta = document.querySelector('meta[name="csrf-token-hash"]');
        var name = nameMeta ? nameMeta.getAttribute('content') : 'csrf_rtn';
        var hash = hashMeta ? hashMeta.getAttribute('content') : '';
        document.querySelectorAll('form input[type="hidden"]').forEach(function (input) {
            if (input.name === name && !input.value && hash) input.value = hash;
        });
        return { name: name, hash: hash };
    }

    function csrfHeaders() {
        var input = csrfInput(), token = input ? input.value : ensureCsrfFields().hash;
        return { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': token || '' };
    }

    function csrfData(data) {
        var input = csrfInput();
        if (!input || !input.value) ensureCsrfFields();
        input = csrfInput();
        if (input) data.set(input.name, input.value);
        return data;
    }

    function updateCsrf(result) {
        var csrf = result && result.csrf;
        if (!csrf || !csrf.name || !csrf.hash) return;
        document.querySelectorAll('input[type="hidden"][name="' + csrf.name + '"]').forEach(function (input) {
            input.value = csrf.hash;
        });
        var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
        if (meta) meta.setAttribute('content', csrf.hash);
        var hashMeta = document.querySelector('meta[name="csrf-token-hash"]');
        if (hashMeta) hashMeta.setAttribute('content', csrf.hash);
    }

    function refreshCsrfToken() {
        return fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var fresh = parsed.querySelector('input[type="hidden"][name="csrf_rtn"]');
                if (!fresh) throw new Error('Your session expired. Refresh the page and try again.');
                document.querySelectorAll('input[type="hidden"][name="' + fresh.name + '"]').forEach(function (input) { input.value = fresh.value; });
                var hashMeta = document.querySelector('meta[name="csrf-token-hash"]');
                if (hashMeta) hashMeta.setAttribute('content', fresh.value);
            });
    }

    function closeModal(modal) {
        if (!modal) return;
        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
            window.jQuery(modal).modal('hide');
            return;
        }
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        modal.style.display = 'none';
        cleanupModalState(modal);
    }

    function cleanupModalState(modal) {
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
            modal.classList.remove('show');
        }
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open', 'rootins-modal-lock');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }
    }

    function openModal(modal) {
        if (!modal || !modal.id) {
            console.error('Shopify mapping modal target is missing.');
            return;
        }
        closeMappingActionMenus();
        if (modal.parentNode !== document.body) document.body.appendChild(modal);
        document.querySelectorAll('.modal.show').forEach(function (other) {
            if (other !== modal) closeModal(other);
        });
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        modal.style.removeProperty('z-index');
        var dialog = modal.querySelector('.modal-dialog');
        if (dialog) dialog.style.removeProperty('z-index');
        if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.modal !== 'function') {
            console.warn('Bootstrap modal plugin is unavailable for #' + modal.id + '; using the local modal fallback.');
            document.querySelectorAll('.modal.show').forEach(function (other) { if (other !== modal) closeModal(other); });
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
            modal.dispatchEvent(new Event('show.bs.modal'));
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('role', 'dialog');
            modal.style.display = 'block';
            document.body.classList.add('modal-open');
            var fallbackBackdrop = document.createElement('div');
            fallbackBackdrop.className = 'modal-backdrop fade show';
            fallbackBackdrop.addEventListener('click', function () { closeModal(modal); });
            document.body.appendChild(fallbackBackdrop);
            modal.dispatchEvent(new Event('shown.bs.modal'));
            return;
        }
        window.jQuery('#' + modal.id).modal('show');
    }

    function setField(form, name, value) {
        var field = form && form.querySelector('[name="' + name + '"]');
        if (field) field.value = value || '';
    }

    function medicineLabel(medicine) {
        return [medicine.medicine_name || medicine.name || 'Medicine', medicine.strength || '', medicine.unit || '']
            .filter(function (part) { return String(part).trim() !== ''; })
            .join(' · ');
    }

    function responseMedicines(payload) {
        if (Array.isArray(payload && payload.medicines)) return payload.medicines;
        if (Array.isArray(payload && payload.data && payload.data.medicines)) return payload.data.medicines;
        if (Array.isArray(payload && payload.data)) return payload.data;
        if (Array.isArray(payload)) return payload;
        return null;
    }

    function showMedicineLoadError(modal, message) {
        var box = modal && modal.querySelector('[data-mapping-errors]');
        if (!box) return;
        box.textContent = message || 'ROOTINS medicines could not be loaded.';
        box.classList.remove('d-none');
    }

    function destroyMedicineSelector(modal) {
        var select = modal && modal.querySelector('#mappingMedicineSelect');
        if (!select || !window.jQuery || !window.jQuery.fn) return;
        var $ = window.jQuery;
        var $select = $(select);
        try {
            if (typeof $.fn.selectpicker === 'function' && ($select.hasClass('selectpicker') || $select.data('selectpicker') || $select.parent('.bootstrap-select').length)) {
                $select.selectpicker('destroy');
            } else if (typeof $.fn.btSelect === 'function' && select.getAttribute('data-btselect-initialized') === 'true') {
                $select.btSelect('destroy');
            }
        } catch (error) {
            console.warn('Could not destroy the ROOTINS medicine selector cleanly.', error);
        }
        select.removeAttribute('data-btselect-initialized');
    }

    function repositionMedicineSelector(modal) {
        var select = modal && modal.querySelector('#mappingMedicineSelect');
        if (!select || !window.jQuery || !window.jQuery.fn) return;
        var $ = window.jQuery;
        var picker = $(select).data('selectpicker');
        if (picker && typeof picker.setPosition === 'function') picker.setPosition();
    }

    function bindMedicineSelectorPositioning(modal) {
        if (!modal || modal.getAttribute('data-medicine-position-bound') === 'true') return;
        var modalBody = modal.querySelector('.modal-body');
        var reposition = function () { repositionMedicineSelector(modal); };
        modal.setAttribute('data-medicine-position-bound', 'true');
        modal._rootinsMedicineReposition = reposition;
        if (modalBody) modalBody.addEventListener('scroll', reposition, { passive: true });
        modal.addEventListener('scroll', reposition, { passive: true });
        window.addEventListener('resize', reposition);
    }

    function initializeMedicineSelector(modal, medicines) {
        var form = modal && modal.querySelector('#shopify-mapping-form');
        var select = modal && modal.querySelector('#mappingMedicineSelect');
        if (!form || !select) return null;

        var selectedValue = select && select.value ? select.value : (form.getAttribute('data-selected-medicine-id') || '');
        if (!Array.isArray(medicines)) medicines = [];
        medicines = medicines.filter(function (medicine) { return String(medicine.status || 'active').toLowerCase() === 'active'; });
        destroyMedicineSelector(modal);
        select.innerHTML = '<option value="">Select ROOTINS medicine</option>';
        medicines.forEach(function (medicine) {
            var option = document.createElement('option');
            option.value = String(medicine.id);
            option.textContent = medicineLabel(medicine);
            select.appendChild(option);
        });
        console.debug('[Shopify mapping medicine]', { response_medicine_count: medicines.length, rendered_option_count: select.options.length, selected_medicine_id: selectedValue || '' });

        select.disabled = medicines.length === 0;
        select.style.width = '100%';
        var emptyMessage = modal.querySelector('[data-medicine-empty]');
        if (emptyMessage) emptyMessage.classList.toggle('d-none', medicines.length !== 0);
        if (select.options.length === 1) {
            return null;
        }
        if (selectedValue && select.querySelector('option[value="' + CSS.escape(String(selectedValue)) + '"]')) select.value = String(selectedValue);

        if (!modal.classList.contains('show')) return select;

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.selectpicker === 'function') {
            var $ = window.jQuery;
            $('#mappingMedicineSelect').selectpicker({
                liveSearch: true,
                liveSearchPlaceholder: 'Search medicine...',
                dropupAuto: false,
                container: 'body',
                width: '100%'
            });
        } else if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.btSelect === 'function') {
            window.jQuery('#mappingMedicineSelect').btSelect({
                liveSearch: true,
                liveSearchPlaceholder: 'Search medicine...',
                dropupAuto: false,
                container: 'body',
                width: '100%'
            });
            select.setAttribute('data-btselect-initialized', 'true');
        }

        bindMedicineSelectorPositioning(modal);
        window.requestAnimationFrame(function () { repositionMedicineSelector(modal); });
        if (selectedValue && select.value === String(selectedValue)) setMedicineValue(form, selectedValue);
        return select;
    }

    function loadMedicines(modal) {
        var url = pageValue('data-medicines-url');
        if (!url) return Promise.resolve(initializeMedicineSelector(modal));
        var select = modal && modal.querySelector('#mappingMedicineSelect');
        if (select) select.setAttribute('aria-busy', 'true');
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(function (response) { return jsonResponse(response).then(function (payload) { if (!response.ok || payload.status === false) throw new Error(payload.msg || 'ROOTINS medicines could not be loaded.'); return payload; }); })
            .then(function (payload) {
                var medicines = responseMedicines(payload);
                if (!medicines) throw new Error('ROOTINS medicines response was invalid.');
                console.debug('[Shopify mapping medicine response]', { response_medicine_count: medicines.length });
                initializeMedicineSelector(modal, medicines);
                return medicines;
            })
            .catch(function (error) {
                showMedicineLoadError(modal, error.message || 'ROOTINS medicines could not be loaded.');
                initializeMedicineSelector(modal, []);
                throw error;
            })
            .finally(function () { if (select) select.removeAttribute('aria-busy'); });
    }

    function setMedicineValue(form, value) {
        var select = form && form.querySelector('#mappingMedicineSelect');
        if (!select) {
            setField(form, 'medicine_id', value);
            return;
        }
        select.value = value || '';
        select.setAttribute('data-selected-medicine-id', value || '');
    }

    function wireModalTargets(root) {
        (root || document).querySelectorAll('.js-map-medicine, .js-edit-mapping').forEach(function (button) {
            button.setAttribute('data-toggle', 'modal');
            button.setAttribute('data-target', '#shopifyMappingModal');
        });
        (root || document).querySelectorAll('.js-more-details').forEach(function (button) {
            button.classList.add('more-details-btn');
            button.setAttribute('data-toggle', 'modal');
            button.setAttribute('data-target', '#shopifyMappingDetailsModal');
        });
        (root || document).querySelectorAll('.js-edit-mapping').forEach(function (button) { button.classList.add('edit-mapping-btn'); });
        (root || document).querySelectorAll('.js-ignore-mapping').forEach(function (button) { button.classList.add('ignore-mapping-btn'); });
        (root || document).querySelectorAll('.js-recheck-item').forEach(function (button) { button.classList.add('reconcile-mapping-btn'); });
        (root || document).querySelectorAll('#mapping-bulk-form [type="submit"]').forEach(function (button) { button.classList.add('bulk-action-btn'); });
        (root || document).querySelectorAll('.js-ignore-mapping').forEach(function (button) {
            button.setAttribute('data-toggle', 'modal');
            button.setAttribute('data-target', '#shopifyIgnoreModal');
        });
    }

    function showProduct(modal, item) {
        var title = modal.querySelector('[data-preview="title"]');
        var variant = modal.querySelector('[data-preview="variant"]');
        var ids = modal.querySelector('[data-preview="ids"]');
        var sku = modal.querySelector('[data-preview="sku"]');
        if (title) title.textContent = item.title_snapshot || item.shopify_product_title || 'Untitled Shopify product';
        if (variant) variant.textContent = 'Variant ' + (item.shopify_variant_id || '—');
        if (ids) ids.textContent = 'Product ' + (item.shopify_product_id || '—') + ' · Variant ' + (item.shopify_variant_id || '—');
        if (sku) sku.textContent = 'SKU ' + (item.sku || item.shopify_sku || 'Not supplied') + ' · Barcode ' + (item.barcode || item.shopify_barcode || 'Not supplied');
    }

    function showMappingDetails(modal, item) {
        function available(value) {
            return value === undefined || value === null || String(value).trim() === '' ? 'Not available' : String(value);
        }
        var values = {
            product: available(item.title_snapshot || item.shopify_product_title),
            product_id: available(item.shopify_product_id),
            variant: available(item.variant_title || item.shopify_variant_title),
            variant_id: available(item.shopify_variant_id),
            sku: available(item.sku || item.shopify_sku),
            barcode: available(item.barcode || item.shopify_barcode),
            handle: available(item.product_handle || item.shopify_product_handle || item.handle),
            type: available(item.product_type || item.shopify_product_type),
            vendor: available(item.vendor || item.shopify_vendor),
            prescription: item.prescription_required ? 'Required' : 'Not required'
        };
        Object.keys(values).forEach(function (key) {
            var field = modal.querySelector('[data-details="' + key + '"]');
            if (field) field.textContent = values[key];
        });
        var image = modal.querySelector('[data-details="image"]');
        var imageUrl = item.image_url || item.product_image_url || '';
        if (image) {
            image.textContent = '';
            if (imageUrl) {
                var imageElement = document.createElement('img');
                imageElement.src = imageUrl;
                imageElement.alt = values.product;
                imageElement.className = 'mapping-details-image';
                image.appendChild(imageElement);
            } else {
                image.textContent = 'Not available';
            }
        }
    }

    function showFormError(form, result) {
        var error = result && result.error ? result.error : {};
        var box = form.querySelector('[data-mapping-errors]');
        var fields = error.fields || {};
        var message = error.message || 'The mapping could not be updated.';
        form.querySelectorAll('[aria-invalid="true"]').forEach(function (field) { field.removeAttribute('aria-invalid'); });
        Object.keys(fields).forEach(function (name) {
            var field = form.querySelector('[name="' + name + '"]');
            if (field) field.setAttribute('aria-invalid', 'true');
        });
        if (Object.keys(fields).length) message += ' ' + Object.keys(fields).map(function (name) { return fields[name]; }).join(' ');
        if (error.correlation_id) message += ' (Reference: ' + error.correlation_id + ')';
        if (box) { box.textContent = message; box.classList.remove('d-none'); }
        return message;
    }

    function isCsrfFailure(response, result) {
        return response.status === 403 && result && result.error && result.error.code === 'SHOPIFY_MAPPING_CSRF_INVALID';
    }

    function fetchWithTimeout(url, options, timeoutMs) {
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timer = window.setTimeout(function () { if (controller) controller.abort(); }, timeoutMs || 30000);
        if (controller) options.signal = controller.signal;
        return fetch(url, options).catch(function (error) {
            if (error && error.name === 'AbortError') {
                var timeout = new Error('MAPPING_REQUEST_TIMEOUT');
                timeout.code = 'MAPPING_REQUEST_TIMEOUT';
                throw timeout;
            }
            throw error;
        }).finally(function () { window.clearTimeout(timer); });
    }

    function invalidResponse(response) {
        var contentType = response.headers.get('content-type') || '';
        return !contentType.toLowerCase().includes('application/json');
    }

    function jsonResponse(response) {
        if (invalidResponse(response)) return Promise.reject(new Error('The mapping request returned an invalid server response.'));
        return response.text().then(function (body) {
            if (!body.trim()) throw new Error('The mapping request returned an invalid server response.');
            try { return JSON.parse(body); } catch (error) { throw new Error('The mapping request returned an invalid server response.'); }
        });
    }

    function refreshWorkspace(message) {
        return fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var fresh = parsed.querySelector('#shopify-order-mappings');
                var token = parsed.querySelector('input[type="hidden"][name="csrf_rtn"]');
                var current = document.querySelector('#shopify-order-mappings');
                if (!fresh || !current) throw new Error('The mapping list could not be refreshed safely.');
                current.innerHTML = fresh.innerHTML;
                if (token) document.querySelectorAll('input[type="hidden"][name="' + token.name + '"]').forEach(function (input) { input.value = token.value; });
                wireModalTargets(current);
                bindSubmitHandler(document.getElementById('mapping-bulk-form'));
                applyCanonicalTab();
                toast(message || 'Shopify mapping updated.');
            });
    }

    function submitForm(form, modal) {
        var medicine = form.querySelector('[name="medicine_id"]');
        var reason = form.querySelector('[name="reason"]');
        var box = form.querySelector('[data-mapping-errors]');
        if (box) { box.textContent = ''; box.classList.add('d-none'); }
        if (medicine && !medicine.value) { if (box) { box.textContent = 'Select an active ROOTINS medicine.'; box.classList.remove('d-none'); } medicine.setAttribute('aria-invalid', 'true'); return; }
        if (reason && !reason.value.trim()) { if (box) { box.textContent = 'A reason is required.'; box.classList.remove('d-none'); } reason.setAttribute('aria-invalid', 'true'); return; }
        if (!window.confirm('Save this Shopify variant to the selected ROOTINS medicine?')) return;
        var button = form.querySelector('[type="submit"]');
        var label = button ? button.innerHTML : '';
        if (button) { button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" aria-hidden="true"></span>Saving…'; }
        (async function () {
            try {
                var response = await fetchWithTimeout(form.getAttribute('data-url'), { method: 'POST', body: csrfData(new FormData(form)), credentials: 'same-origin', headers: csrfHeaders() }, 30000);
                var result = await jsonResponse(response);
                updateCsrf(result);
                if (isCsrfFailure(response, result)) {
                    await refreshCsrfToken().catch(function () {});
                    throw new Error('Your session expired. Refresh the page and try again.');
                }
                if (!response.ok || !result.status) { var error = new Error(showFormError(form, result)); error.displayed = true; throw error; }
                toast((result.data && result.data.message) || 'Product mapping saved.');
                closeModal(modal);
                refreshWorkspace().catch(function () { toast('The mapping was saved, but the list could not be refreshed.', true); });
            } catch (error) {
                if (!error.displayed) toast(error.code || error.message || 'The action could not be completed safely.', true);
            } finally {
                if (button) { button.disabled = false; button.innerHTML = label; }
            }
        }());
    }

    function submitBulkForm(form) {
        closeMappingActionMenus();
        var selected = Array.prototype.map.call(document.querySelectorAll('.mapping-select:checked'), function (input) {
            return { product_id: input.getAttribute('data-product-id'), variant_id: input.value, title: input.getAttribute('data-title') || '' };
        });
        var medicine = form.querySelector('[name="medicine_id"]');
        var button = form.querySelector('[type="submit"]');
        if (!selected.length || !medicine || !medicine.value) { toast('Choose at least one Shopify variant and a ROOTINS medicine.', true); return; }
        if (!window.confirm('Apply this medicine to ' + selected.length + ' Shopify variant(s)?')) return;
        var label = button ? button.innerHTML : '';
        if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" aria-hidden="true"></span>Saving…'; }
        var data = new FormData(form);
        data.set('variants', JSON.stringify(selected));
        postForm(form.getAttribute('data-url'), data, function () { toast('Your session expired. Refresh the page and try again.', true); })
            .then(function (result) { toast(result.msg || 'Product mappings saved.'); return refreshWorkspace(); })
            .catch(function (error) { toast(error.message || 'Bulk mapping could not be completed safely.', true); })
            .finally(function () { if (button) { button.disabled = false; button.removeAttribute('aria-busy'); button.innerHTML = label; } });
    }

    function postForm(url, data, onCsrfFailure) {
        return fetchWithTimeout(url, { method: 'POST', body: csrfData(data), credentials: 'same-origin', headers: csrfHeaders() }, 30000)
            .then(function (response) { return jsonResponse(response).then(function (result) { updateCsrf(result); if (isCsrfFailure(response, result)) { return refreshCsrfToken().catch(function () {}).then(function () { onCsrfFailure(); throw new Error('Your session expired. Refresh the page and try again.'); }); } if (!response.ok || !result.status) throw new Error(result.error_category || (result.error && result.error.code) || result.msg || 'The Shopify operation could not be completed safely.'); return result; }); });
    }

    function applyCanonicalTab() {
        var params = new URLSearchParams(window.location.search);
        var status = ['needs', 'mapped', 'conflicts', 'inactive'].indexOf(params.get('mapping_status')) >= 0 ? params.get('mapping_status') : 'needs';
        document.querySelectorAll('.mapping-tabs .nav-link').forEach(function (link) {
            var linkStatus = new URL(link.href, window.location.href).searchParams.get('mapping_status');
            var active = linkStatus === status;
            link.classList.toggle('active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        ['needs', 'mapped', 'conflicts', 'inactive'].forEach(function (name) { var section = document.getElementById('mapping-' + name); if (section) section.style.display = name === status ? 'block' : 'none'; });
    }

    function closeMappingActionMenus() {
        document.querySelectorAll(
            '#mappingResults .dropdown-menu.show, #mappingResults .more-actions-menu.show'
        ).forEach(function (menu) {
            if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.dropdown === 'function') {
                var toggle = menu.closest('.dropdown') && menu.closest('.dropdown').querySelector('[data-toggle="dropdown"]');
                if (toggle) window.jQuery(toggle).dropdown('hide');
            }
            if (window.bootstrap && window.bootstrap.Dropdown) {
                var bootstrapToggle = menu.closest('.dropdown') && menu.closest('.dropdown').querySelector('.dropdown-toggle');
                if (bootstrapToggle) {
                    var instance = window.bootstrap.Dropdown.getInstance(bootstrapToggle);
                    if (instance) instance.hide();
                }
            }
            menu.classList.remove('show');
            menu.style.display = '';
        });
        document.querySelectorAll(
            '#mappingResults [data-toggle="dropdown"].show, #mappingResults [aria-expanded="true"]'
        ).forEach(function (toggle) {
            toggle.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
        });
        document.body.classList.remove('dropdown-open');
    }

    function closeDropdown() { closeMappingActionMenus(); }
    function closeAllDropdowns() { closeMappingActionMenus(); }

    function bindModalLifecycle(modal) {
        var onShow = function () {
            modal.setAttribute('aria-hidden', 'false');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('role', 'dialog');
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop, index, all) { if (index < all.length - 1) backdrop.remove(); });
        };
        var onShown = function () {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            modal.setAttribute('aria-modal', 'true');
            if (modal.id === 'shopifyMappingModal') loadMedicines(modal).catch(function () {});
        };
        var onHide = function () { modal.setAttribute('aria-hidden', 'true'); modal.removeAttribute('aria-modal'); };
        var onHidden = function () {
            if (modal.id === 'shopifyMappingModal') destroyMedicineSelector(modal);
            var box = modal.querySelector('[data-mapping-errors]');
            if (box) { box.textContent = ''; box.classList.add('d-none'); }
            modal.querySelectorAll('[aria-invalid="true"]').forEach(function (field) { field.removeAttribute('aria-invalid'); });
            setMedicineValue(modal.querySelector('form'), '');
            var mappingForm = modal.querySelector('#shopify-mapping-form');
            if (mappingForm) mappingForm.removeAttribute('data-selected-medicine-id');
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
            }
            modal.style.removeProperty('z-index');
            var dialog = modal.querySelector('.modal-dialog');
            if (dialog) dialog.style.removeProperty('z-index');
            window.setTimeout(function () { var form = modal.querySelector('form'); if (form && !modal.classList.contains('show')) form.reset(); }, 0);
        };
        modal.addEventListener('show.bs.modal', onShow);
        modal.addEventListener('shown.bs.modal', onShown);
        modal.addEventListener('hide.bs.modal', onHide);
        modal.addEventListener('hidden.bs.modal', onHidden);
        if (window.jQuery) window.jQuery(modal).on('show.bs.modal.rootins shown.bs.modal.rootins hide.bs.modal.rootins hidden.bs.modal.rootins', function (event) {
            ({ show: onShow, shown: onShown, hide: onHide, hidden: onHidden })[event.type.split('.')[0]]();
        });
    }

    function bindSubmitHandler(form) {
        if (!form || form.getAttribute('data-rootins-submit-bound') === 'true') return;
        form.setAttribute('data-rootins-submit-bound', 'true');
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            submitForm(form, form.closest('.modal'));
            return false;
        });
    }

    ready(function () {
        applyCanonicalTab();
        window.addEventListener('popstate', applyCanonicalTab);
        window.addEventListener('hashchange', applyCanonicalTab);
        var mappingModal = document.getElementById('shopifyMappingModal');
        var ignoreModal = document.getElementById('shopifyIgnoreModal');
        var detailsModal = document.getElementById('shopifyMappingDetailsModal');
        var syncDetailsModal = document.getElementById('shopifySyncDetailsModal');
        ensureCsrfFields();
        wireModalTargets(document);
        [mappingModal, ignoreModal, detailsModal, syncDetailsModal].forEach(function (modal) {
            if (!modal) return;
            if (modal.parentNode !== document.body) document.body.appendChild(modal);
            var modalDialog = modal.querySelector('.modal-dialog');
            if (modalDialog) modalDialog.classList.add('modal-dialog-scrollable');
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-hidden', 'true');
            modal.style.display = 'none';
            bindModalLifecycle(modal);
            bindSubmitHandler(modal.querySelector('form'));
            if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.modal !== 'function') {
                modal.querySelectorAll('[data-dismiss="modal"], .close').forEach(function (button) { button.addEventListener('click', function () { closeModal(modal); }); });
                modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(modal); });
            }
        });

        document.addEventListener('click', function (event) {
            var dropdownItem = event.target.closest('.mapping-actions-cell .dropdown-menu .dropdown-item');
            var dropdownToggle = event.target.closest('.mapping-actions-cell [data-toggle="dropdown"]');
            if (dropdownItem) closeDropdown(dropdownItem.closest('.dropdown-menu'));
            else if (dropdownToggle) closeAllDropdowns(dropdownToggle.closest('.dropdown'));
            else if (!event.target.closest('.mapping-actions-cell .dropdown')) closeAllDropdowns();
            var dismiss = event.target.closest('[data-dismiss="modal"], .close');
            if (dismiss) { var dismissModal = dismiss.closest('.modal'); if (dismissModal) { event.preventDefault(); closeModal(dismissModal); return; } }
            var backdropTarget = event.target.closest('.modal.show');
            if (backdropTarget && event.target === backdropTarget) { closeModal(backdropTarget); return; }
            var mapButton = event.target.closest('.js-map-medicine, .js-edit-mapping');
            var ignoreButton = event.target.closest('.js-ignore-mapping');
            var detailsButton = event.target.closest('.js-more-details');
            var syncButton = event.target.closest('.js-sync-products');
            var recheck = event.target.closest('.js-recheck-all, .js-recheck-item');
            if (mapButton || ignoreButton || detailsButton || syncButton || recheck) closeMappingActionMenus();
            if (mapButton && mappingModal) { event.preventDefault(); event.stopImmediatePropagation(); var item = parse(mapButton.getAttribute('data-item') || mapButton.getAttribute('data-mapping')), form = mappingModal.querySelector('form'); form.reset(); showProduct(mappingModal, item); Object.keys(item).forEach(function (key) { if (key !== 'medicine_id') setField(form, key, item[key]); }); form.setAttribute('data-selected-medicine-id', item.medicine_id || ''); openModal(mappingModal); }
            if (ignoreButton && ignoreModal) { event.preventDefault(); event.stopImmediatePropagation(); var ignored = parse(ignoreButton.getAttribute('data-item')), ignoreForm = ignoreModal.querySelector('form'); ignoreForm.reset(); setField(ignoreForm, 'shopify_product_id', ignored.shopify_product_id); setField(ignoreForm, 'shopify_variant_id', ignored.shopify_variant_id); openModal(ignoreModal); }
            if (detailsButton && detailsModal) { event.preventDefault(); event.stopImmediatePropagation(); showMappingDetails(detailsModal, parse(detailsButton.getAttribute('data-item'))); openModal(detailsModal); }
            var syncButton = event.target.closest('.js-sync-products'); if (syncButton) { event.preventDefault(); event.stopImmediatePropagation(); var syncLabel = syncButton.innerHTML; syncButton.disabled = true; postForm(pageValue('data-sync-url'), new FormData(), function () { toast('Your session expired. Refresh the page and try again.', true); }).then(function () { return refreshWorkspace('Shopify products synchronized.'); }).catch(function (error) { toast(error.message, true); }).finally(function () { syncButton.disabled = false; syncButton.innerHTML = syncLabel; }); }
            var recheck = event.target.closest('.js-recheck-all, .js-recheck-item'); if (recheck) { event.preventDefault(); recheck.disabled = true; var data = new FormData(); data.append('store_domain', recheck.getAttribute('data-store') || pageValue('data-store-domain')); data.append('shopify_variant_id', recheck.getAttribute('data-variant') || 'all'); postForm(pageValue('data-recheck-url'), data, function () { toast('Your session expired. Refresh the page and try again.', true); }).then(function (result) { toast(result.msg || 'Affected orders rechecked.'); }).catch(function (error) { toast(error.message, true); }).finally(function () { recheck.disabled = false; }); }
        }, true);
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { closeAllDropdowns(); document.querySelectorAll('.modal.show').forEach(closeModal); } }, true);
        document.addEventListener('change', function (event) { if (event.target.id === 'mappingMedicineSelect') { setMedicineValue(document.getElementById('shopify-mapping-form'), event.target.value); console.debug('[Shopify mapping medicine]', { selected_medicine_id: event.target.value }); } });
        bindSubmitHandler(document.getElementById('mapping-bulk-form'));
    });

    function pageValue(name) { var page = document.getElementById('shopify-order-mappings'); return page ? page.getAttribute(name) || '' : ''; }
}(window, document));
