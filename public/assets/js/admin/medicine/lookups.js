(function (window, document) {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, {once: true});
        else callback();
    }

    ready(function () {
        var form = document.getElementById('medicineLookupForm');
        var modal = document.getElementById('medicineLookupEditModal');
        if (!form || !modal) return;
        var type = String((form.querySelector('[name="type"]') || {}).value || '');
        var base = (document.querySelector('meta[name="base-url"]') || {}).content || '/';
        var submit = form.querySelector('button[type="submit"]');

        function csrf() {
            var token = form.querySelector('input[name="csrf_rtn"]') || document.querySelector('input[name="csrf_rtn"]');
            return token ? token.value : '';
        }
        function loader(show) {
            if (typeof window.show_loader === 'function' && show) window.show_loader();
            if (typeof window.hide_loader === 'function' && !show) window.hide_loader();
            var preloader = document.querySelector('.preloader');
            if (preloader && !show) preloader.style.display = 'none';
        }
        function message(text, error) {
            if (error && typeof window.showError === 'function') window.showError(text);
            else if (!error && typeof window.showSuccess === 'function') window.showSuccess(text);
            else { var alert = document.getElementById('js_alert'); if (alert) { alert.textContent = text; alert.className = error ? 'alert alert-danger' : 'alert alert-success'; } }
        }
        function clearErrors() {
            form.querySelectorAll('.validation-error').forEach(function (node) { node.textContent = ''; });
            form.querySelectorAll('.is-invalid').forEach(function (node) { node.classList.remove('is-invalid'); });
        }
        function bindErrors(errors) {
            Object.keys(errors || {}).forEach(function (field) {
                var input = form.querySelector('[name="' + field.replace(/"/g, '\\"') + '"]');
                var error = document.getElementById(field + 'Err') || (input && input.parentElement.querySelector('.validation-error'));
                if (input) input.classList.add('is-invalid');
                if (error) error.textContent = errors[field];
            });
        }
        function resetForm() {
            form.reset();
            form.querySelector('[name="id"]').value = '';
            var status = form.querySelector('[name="status"]');
            if (status) status.checked = true;
            clearErrors();
            modal.querySelector('.modal-title').textContent = 'New option';
            if (submit) { submit.disabled = false; submit.removeAttribute('aria-busy'); }
        }
        function setValues(row) {
            resetForm();
            form.querySelector('[name="id"]').value = String(row.id || '');
            form.querySelector('[name="name"]').value = row.name || '';
            var code = form.querySelector('[name="code"]');
            if (code) code.value = row.code || '';
            var status = form.querySelector('[name="status"]');
            if (status) status.checked = Number(row.status) === 1;
            modal.querySelector('.modal-title').textContent = 'Edit option';
        }
        function closeModal() {
            modal.classList.remove('show'); modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            var backdrop = document.querySelector('.medicine-lookup-backdrop'); if (backdrop) backdrop.remove();
        }
        function openModal() {
            var oldBackdrop = document.querySelector('.medicine-lookup-backdrop'); if (oldBackdrop) oldBackdrop.remove();
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show medicine-lookup-backdrop';
            backdrop.addEventListener('click', closeModal); document.body.appendChild(backdrop);
            modal.classList.add('show'); modal.style.display = 'block'; modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('modal-open');
            var dialog = modal.querySelector('.modal-dialog'); if (dialog) { dialog.setAttribute('tabindex', '-1'); dialog.focus(); }
        }
        function request(url, options) {
            loader(true); options = options || {}; options.credentials = 'same-origin';
            options.headers = Object.assign({'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf()}, options.headers || {});
            var controller = window.AbortController ? new AbortController() : null;
            var timer = controller ? window.setTimeout(function () { controller.abort(); }, 15000) : null;
            if (controller) options.signal = controller.signal;
            return fetch(url, options).then(function (response) { return response.json().catch(function () { return {}; }).then(function (data) { if (!response.ok) { var error = new Error(data.msg || 'The request could not be completed.'); error.data = data; throw error; } return data; }); }).finally(function () { if (timer) window.clearTimeout(timer); loader(false); });
        }

        document.getElementById('openMedicineLookupModal')?.addEventListener('click', function (event) { event.preventDefault(); resetForm(); openModal(); });
        document.addEventListener('click', function (event) {
            var edit = event.target.closest('.edit-medicine-lookup');
            if (edit) {
                event.preventDefault();
                var id = Number(edit.dataset.id); var rowType = String(edit.dataset.lookupType || type);
                if (!Number.isInteger(id) || id < 1 || !rowType) { message('The lookup record is invalid.', true); return; }
                setValues({id: id, name: edit.dataset.value || '', status: Number(edit.dataset.status) === 1 ? 1 : 0}); openModal();
                request(base + 'admin/medicine-lookups/edit/' + encodeURIComponent(rowType) + '/' + id, {method: 'GET'}).then(function (response) { if (response.status && response.data) setValues(response.data); else message(response.msg || 'The lookup option could not be loaded.', true); }).catch(function (error) { message((error.data && error.data.msg) || error.message || 'The lookup option could not be loaded.', true); });
                return;
            }
            var close = event.target.closest('[data-dismiss="modal"]');
            if (close && close.closest('#medicineLookupEditModal')) { event.preventDefault(); closeModal(); return; }
            var toggle = event.target.closest('.medicine-lookups-page .dropdown-toggle');
            if (toggle) { event.preventDefault(); var menu = toggle.parentElement.querySelector('.dropdown-menu'); document.querySelectorAll('.medicine-lookups-page .dropdown-menu.show').forEach(function (open) { if (open !== menu) open.classList.remove('show'); }); if (menu) { menu.classList.toggle('show'); toggle.setAttribute('aria-expanded', menu.classList.contains('show') ? 'true' : 'false'); } }
        });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
        form.addEventListener('submit', function (event) {
            event.preventDefault(); if (submit && submit.disabled) return; clearErrors(); if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
            request(form.action || base + 'admin/medicine-lookups/save/' + encodeURIComponent(type), {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: new URLSearchParams(new FormData(form)).toString()}).then(function (response) { if (response.status) { closeModal(); message(response.msg || 'Lookup option saved.', false); window.setTimeout(function () { window.location.reload(); }, 500); return; } bindErrors(response.errors || {}); message(response.msg || 'Please correct the highlighted fields.', true); }).catch(function (error) { bindErrors((error.data && error.data.errors) || {}); message((error.data && error.data.msg) || error.message || 'The lookup option could not be saved safely.', true); }).finally(function () { if (submit) { submit.disabled = false; submit.removeAttribute('aria-busy'); } loader(false); });
        });
    });
}(window, document));
