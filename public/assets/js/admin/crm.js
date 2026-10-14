(function () {
    'use strict';

    if (window.rootinsCrmBound && window.rootinsCrmModalLifecycleVersion) {
        return;
    }
    window.rootinsCrmBound = true;
    window.rootinsCrmModalLifecycleVersion = true;

    function clear(form) {
        form.querySelectorAll('.crm-error').forEach(function (error) {
            error.textContent = '';
        });
        form.querySelectorAll('.is-invalid').forEach(function (field) {
            field.classList.remove('is-invalid');
        });
    }

    function showErrors(form, data) {
        Object.keys(data || {}).forEach(function (key) {
            var field = form.querySelector('[name="' + key + '"]');
            var error = form.querySelector('[data-error-for="' + key + '"]');
            if (field) {
                field.classList.add('is-invalid');
            }
            if (error) {
                error.textContent = data[key];
            }
        });
    }

    function formatTime(value) {
        var match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
        if (!match) {
            return value;
        }
        var hour = parseInt(match[1], 10);
        var suffix = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;
        return String(hour).padStart(2, '0') + ':' + match[2] + ' ' + suffix;
    }

    function formatFollowupTimes() {
        document.querySelectorAll('.crm-followup-datetime small').forEach(function (node) {
            node.textContent = formatTime(node.textContent);
        });
        document.querySelectorAll('.crm-followups-table form').forEach(function (form) {
            form.classList.add('js-crm-form');
            form.setAttribute('data-no-global-loader', '1');
            var inlineConfirmation = form.getAttribute('onsubmit');
            if (inlineConfirmation) {
                form.removeAttribute('onsubmit');
            }
            if (!form.dataset.confirm) {
                var actionButton = form.querySelector('button[type="submit"]');
                var action = actionButton ? (actionButton.textContent || '').trim().toLowerCase() : '';
                form.dataset.confirm = action === 'complete' ? 'Mark this follow-up as completed?' : action === 'cancel' ? 'Cancel this follow-up?' : action === 'mark missed' ? 'Mark this follow-up as missed?' : '';
            }
        });
    }

    function confirmFormAction(form, event) {
        var message = form.dataset.confirm;
        if (!message) {
            return true;
        }
        if (window.confirm(message)) {
            return true;
        }
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
        return false;
    }

    function formatDate(value) {
        var match = String(value || '').slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return match ? match[3] + '-' + match[2] + '-' + match[1] : (value || '—');
    }

    function formatDateTime(value) {
        var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        return match ? match[3] + '-' + match[2] + '-' + match[1] + ' ' + formatTime(match[4] + ':' + match[5]) : (value || '—');
    }

    function titleCase(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    }

    function displayActivity(activity) {
        var target = document.querySelector('[data-followup-activity]');
        if (!target) return;
        target.innerHTML = '';
        if (!activity || !activity.length) {
            target.innerHTML = '<div class="crm-empty"><span>No activity recorded yet.</span></div>';
            return;
        }
        activity.forEach(function (event) {
            var previous = parseActivityValue(event.previous_value);
            var next = parseActivityValue(event.new_value);
            var change = '';
            if (previous || next) {
                change = '<div class="crm-followup-change"><div><span>Previous value</span>' + escapeText(formatActivityValue(previous)) + '</div><div><span>New value</span>' + escapeText(formatActivityValue(next)) + '</div></div>';
            }
            var card = document.createElement('article');
            card.className = 'crm-followup-event';
            card.innerHTML = '<header><strong>' + escapeText(titleCase(event.event_type)) + '</strong><time>' + escapeText(formatDateTime(event.created_at)) + '</time></header><small>' + escapeText(event.actor_name || 'System') + '</small>' + change + (event.remarks ? '<p>' + escapeText(event.remarks) + '</p>' : '');
            target.appendChild(card);
        });
    }

    function parseActivityValue(value) {
        if (!value) return null;
        try {
            return typeof value === 'string' ? JSON.parse(value) : value;
        } catch (error) {
            return { value: value };
        }
    }

    function formatActivityValue(value) {
        if (!value) return '—';
        return Object.keys(value).map(function (key) {
            if (/^(id|.*_id|.*_by|follow_up_number)$/i.test(key)) return '';
            var item = value[key];
            if (/time$/i.test(key)) item = formatTime(item);
            if (/date$/i.test(key)) item = formatDate(item);
            if (/status$/i.test(key)) item = titleCase(item);
            return titleCase(key) + ': ' + (item === null || item === '' ? '—' : item);
        }).filter(Boolean).join(' · ') || '—';
    }

    function escapeText(value) {
        return String(value === undefined || value === null ? '—' : value).replace(/[&<>"']/g, function (character) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]; });
    }

    function followupActionUrl(template, id) {
        return String(template || '').replace(/(?:\{id\}|%7Bid%7D)/gi, encodeURIComponent(String(id || '')));
    }

    function redesignFollowupDetailsModal() {
        var instances = document.querySelectorAll('#crmFollowUpDetailsModal');
        instances.forEach(function (modal, index) { if (index > 0) modal.remove(); });
        var target = instances[0];
        if (!target || target.dataset.redesigned === '1') return;
        var dialog = target.querySelector('.modal-dialog');
        if (!dialog) {
            dialog = document.createElement('div');
            dialog.className = 'modal-dialog modal-dialog-centered modal-lg';
            target.appendChild(dialog);
        }
        var typeOptions = target.querySelector('[data-followup-panel="reschedule"] select[name="follow_up_type_id"]');
        var assignedOptions = target.querySelector('[data-followup-panel="reschedule"] select[name="assigned_user_id"]');
        var commentTemplate = target.querySelector('[data-followup-panel="comment"] form')?.dataset.actionTemplate || '';
        var rescheduleTemplate = target.querySelector('[data-followup-panel="reschedule"] form')?.dataset.actionTemplate || '';
        dialog.innerHTML = '<div class="modal-content"><div class="modal-header"><div><span class="crm-eyebrow">CRM FOLLOW-UP</span><h2 class="modal-title h5">Follow-up details</h2></div><span class="badge crm-status-badge crm-status-scheduled" data-detail="status">Scheduled</span><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div><div class="modal-body"><section class="crm-followup-lead-card"><span class="crm-eyebrow">LEAD</span><strong class="crm-lead-primary" data-detail="lead">—</strong><div><span data-detail="lead_number">—</span><span data-detail="mobile">—</span></div><div class="crm-followup-lead-meta"><span><small>Interested service</small><strong data-detail="service">—</strong></span><span><small>Assigned user</small><strong data-detail="assigned">Unassigned</strong></span></div></section><section class="crm-followup-summary-section"><h3>Follow-up summary</h3><div class="crm-followup-summary-grid"><div><small>Date</small><strong data-detail="date">—</strong></div><div><small>Time</small><strong data-detail="time">—</strong></div><div><small>Follow-up type</small><strong data-detail="type">—</strong></div><div><small>Current status</small><strong class="badge crm-status-badge crm-status-scheduled" data-detail="status">Scheduled</strong></div><div><small>Created date</small><strong data-detail="created">—</strong></div><div><small>Last updated date</small><strong data-detail="updated">—</strong></div></div></section><section class="crm-followup-remarks-section"><h3>Remarks</h3><div class="crm-followup-remarks-block" data-detail="remarks">No remarks added</div></section><section id="activity" class="crm-followup-activity"><div class="crm-section-heading"><div><span class="crm-eyebrow">HISTORY</span><h3>Recent activity</h3></div></div><div class="crm-followup-timeline" data-followup-activity><div class="crm-empty"><span>Loading activity…</span></div></div></section><section class="crm-followup-action-panel d-none" data-followup-panel="comment"><h3>Add comment</h3><form class="js-crm-form" data-no-global-loader="1" method="post" data-action-template=""><div class="form-group"><label for="crmFollowUpComment">Comment</label><textarea class="form-control" id="crmFollowUpComment" name="comment" rows="4" maxlength="2000" required></textarea><div class="crm-error" data-error-for="comment"></div></div><button class="btn btn-dark" type="submit">Save comment</button></form></section><section class="crm-followup-action-panel d-none" data-followup-panel="reschedule"><h3>Reschedule follow-up</h3><form class="js-crm-form" data-no-global-loader="1" method="post" data-action-template=""><div class="crm-form-grid"><div class="form-group"><label for="crmRescheduleDate">Follow-up date</label><input class="form-control" id="crmRescheduleDate" name="follow_up_date" type="date" required><div class="crm-error" data-error-for="follow_up_date"></div></div><div class="form-group"><label for="crmRescheduleTime">Follow-up time</label><input class="form-control" id="crmRescheduleTime" name="follow_up_time" type="time" required><div class="crm-error" data-error-for="follow_up_time"></div></div><div class="form-group"><label for="crmRescheduleType">Follow-up type</label><select class="form-control" id="crmRescheduleType" name="follow_up_type_id" required>' + (typeOptions ? typeOptions.innerHTML : '') + '</select><div class="crm-error" data-error-for="follow_up_type_id"></div></div><div class="form-group"><label for="crmRescheduleAssigned">Assigned user</label><select class="form-control" id="crmRescheduleAssigned" name="assigned_user_id">' + (assignedOptions ? assignedOptions.innerHTML : '<option value="">Unassigned</option>') + '</select><div class="crm-error" data-error-for="assigned_user_id"></div></div><div class="form-group crm-wide"><label for="crmRescheduleComment">Reason or comment</label><textarea class="form-control" id="crmRescheduleComment" name="comment" rows="3" maxlength="2000"></textarea><div class="crm-error" data-error-for="comment"></div></div></div><button class="btn btn-dark" type="submit">Save reschedule</button></form></section></div><div class="modal-footer"><div class="crm-followup-footer-actions"><button type="button" class="btn btn-light" data-dismiss="modal">Close</button></div></div></div>';
        target.querySelector('[data-followup-panel="comment"] form').dataset.actionTemplate = commentTemplate;
        target.querySelector('[data-followup-panel="reschedule"] form').dataset.actionTemplate = rescheduleTemplate;
        target.dataset.redesigned = '1';
    }

    async function loadFollowupDetails(button) {
        var target = document.querySelector('#crmFollowUpDetailsModal');
        if (!target || !button.dataset.followupUrl) return;
        var activeControl = button instanceof Element ? button : null;
        if (activeControl) {
            activeControl.disabled = true;
            activeControl.setAttribute('aria-busy', 'true');
        }
        if (window.show_loader) {
            window.show_loader();
        }
        target.classList.add('crm-followup-details-modal');
        target.querySelectorAll('[data-followup-panel]').forEach(function (panel) { panel.classList.toggle('d-none', panel.dataset.followupPanel !== (button.dataset.followupMode || '')); });
        target.querySelectorAll('[data-detail]').forEach(function (field) { field.textContent = 'Loading…'; });
        target.querySelectorAll('form[data-action-template]').forEach(function (form) {
            if (form.querySelector('[name="csrf_rtn"]')) return;
            var token = document.querySelector('[name="csrf_rtn"]');
            if (!token) return;
            var hiddenToken = document.createElement('input');
            hiddenToken.type = 'hidden';
            hiddenToken.name = 'csrf_rtn';
            hiddenToken.value = token.value;
            form.appendChild(hiddenToken);
        });
        try {
            var response = await fetch(button.dataset.followupUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            var payload = await response.json();
            if (!response.ok || !payload.status) throw new Error(payload.msg || 'Unable to load follow-up details.');
            var item = payload.data.follow_up || {};
            target.dataset.followupId = button.dataset.followupId || '';
            target.dataset.followupUrl = button.dataset.followupUrl || '';
            var followUpTime = formatTime(item.follow_up_time);
            var values = {lead:item.full_name, lead_number:item.lead_number, mobile:item.mobile_display, follow_up:formatDate(item.follow_up_date) + ' · ' + followUpTime, type:item.follow_up_type_name, status:titleCase(item.follow_up_status), assigned:item.assigned_user_name || 'Unassigned', remarks:item.remarks || '—', created:formatDateTime(item.created_at), creator:item.creator_name || 'Unknown creator', updated:formatDateTime(item.updated_at), updater:item.updater_name || 'Unknown updater'};
            values.service = item.service_name || '—';
            values.date = formatDate(item.follow_up_date);
            values.time = followUpTime;
            target.querySelectorAll('[data-detail]').forEach(function (field) {
                field.textContent = values[field.dataset.detail] || '—';
                if (field.dataset.detail === 'status') {
                    field.className = 'badge crm-status-badge crm-status-' + String(item.follow_up_status || '');
                }
            });
            ['creator', 'updater'].forEach(function (key) {
                var field = target.querySelector('[data-detail="' + key + '"]');
                if (field && field.closest('div')) field.closest('div').remove();
            });
            var activityHeading = target.querySelector('.crm-followup-activity h3');
            if (activityHeading) activityHeading.textContent = 'Recent activity';
            target.querySelectorAll('[data-followup-status-action]').forEach(function (form) {
                form.action = button.dataset.followupUrl + '/' + form.dataset.followupStatusAction;
            });
            displayActivity(payload.data.activity || []);
            var reschedule = target.querySelector('[data-followup-panel="reschedule"] form');
            if (reschedule) { reschedule.action = followupActionUrl(reschedule.dataset.actionTemplate, button.dataset.followupId); reschedule.querySelector('[name="follow_up_date"]').value = item.follow_up_date || ''; reschedule.querySelector('[name="follow_up_time"]').value = String(item.follow_up_time || '').slice(0, 5); reschedule.querySelector('[name="follow_up_type_id"]').value = item.follow_up_type_id || ''; reschedule.querySelector('[name="assigned_user_id"]').value = item.assigned_user_id || ''; }
            var comment = target.querySelector('[data-followup-panel="comment"] form');
            if (comment) { comment.action = followupActionUrl(comment.dataset.actionTemplate, button.dataset.followupId); comment.reset(); }
        } catch (error) {
            var activity = target.querySelector('[data-followup-activity]');
            if (activity) activity.innerHTML = '<div class="alert alert-danger">' + escapeText(error.message) + '</div>';
        } finally {
            if (activeControl) {
                activeControl.disabled = false;
                activeControl.removeAttribute('aria-busy');
            }
            if (window.hide_loader) {
                window.hide_loader();
            }
        }
    }

    function clearFollowupModalState(modal) {
        modal.dataset.followupId = '';
        modal.dataset.followupUrl = '';
        modal.querySelectorAll('[data-followup-panel]').forEach(function (panel) { panel.classList.add('d-none'); });
        modal.querySelectorAll('form').forEach(function (form) { form.reset(); clear(form); });
        modal.querySelectorAll('[data-detail]').forEach(function (field) { field.textContent = field.dataset.detail === 'remarks' ? 'No remarks added' : '—'; });
        var activity = modal.querySelector('[data-followup-activity]');
        if (activity) activity.innerHTML = '<div class="crm-empty"><span>Select a follow-up to view activity.</span></div>';
    }

    function removeCrmBackdrops() {
        document.querySelectorAll('.modal-backdrop.crm-modal-backdrop, .modal-backdrop').forEach(function (node) { node.remove(); });
    }

    function restoreCrmBody() {
        if (document.querySelector('.modal.show')) return;
        document.body.classList.remove('modal-open', 'rootins-modal-lock');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        removeCrmBackdrops();
    }

    function showCrmModal(modal, trigger) {
        if (!modal) return;
        modal._crmTrigger = trigger && trigger instanceof Element ? trigger : null;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(modal).modal('show');
            return;
        }
        removeCrmBackdrops();
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show crm-modal-backdrop';
        document.body.appendChild(backdrop);
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        modal.setAttribute('role', 'dialog');
        document.body.classList.add('modal-open', 'rootins-modal-lock');
        document.body.style.overflow = 'hidden';
        var body = modal.querySelector('.modal-body');
        if (body) { body.setAttribute('tabindex', '-1'); body.focus({preventScroll: true}); }
    }

    function hideCrmModal(modal) {
        if (!modal) return;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(modal).modal('hide');
        } else {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            restoreCrmBody();
        }
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        restoreCrmBody();
        clearFollowupModalState(modal);
        if (modal._crmTrigger && typeof modal._crmTrigger.focus === 'function') modal._crmTrigger.focus();
    }

    function bindCrmModalFallback() {
        if (window.rootinsCrmModalFallbackBound) return;
        window.rootinsCrmModalFallbackBound = true;
        document.addEventListener('click', function (event) {
            var dismiss = event.target.closest('[data-dismiss="modal"]');
            var modal = event.target.closest('.crm-modal');
            if (dismiss && modal) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
                hideCrmModal(modal);
                return;
            }
            if (modal && event.target === modal && modal.dataset.backdrop !== 'static') {
                hideCrmModal(modal);
            }
        }, true);
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var modal = document.querySelector('.crm-modal.show');
            if (!modal || modal.dataset.keyboard === 'false') return;
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
            hideCrmModal(modal);
        }, true);
    }

    function cleanupModalArtifacts() {
        restoreCrmBody();
    }

    function bindModalLifecycle() {
        if (!window.jQuery || !window.jQuery.fn.modal || window.rootinsCrmModalLifecycleBound) {
            return;
        }
        window.rootinsCrmModalLifecycleBound = true;
        window.jQuery(document)
            .on('shown.bs.modal', '.crm-modal', function () {
                var modal = this;
                var backdrop = document.querySelector('.modal-backdrop:not(.crm-modal-backdrop)');
                if (backdrop) {
                    backdrop.classList.add('crm-modal-backdrop');
                }
                var body = modal.querySelector('.modal-body');
                if (body) {
                    body.setAttribute('tabindex', '-1');
                    body.focus({preventScroll: true});
                }
            })
            .on('hidden.bs.modal', '.crm-modal', cleanupModalArtifacts)
            .on('hide.bs.modal', '.crm-modal', function () {
                this.querySelectorAll('.is-invalid').forEach(function (field) {
                    field.classList.remove('is-invalid');
                });
            });
    }

    document.addEventListener('DOMContentLoaded', bindModalLifecycle);
    bindModalLifecycle();
    formatFollowupTimes();
    redesignFollowupDetailsModal();
    bindCrmModalFallback();

    document.addEventListener('click', function (event) {
        var reset = event.target.closest('[data-crm-reset="1"]');
        var edit = event.target.closest('[data-crm-edit]');

        if (reset) {
            var resetForm = document.querySelector('#crmLeadModal form');
            if (resetForm) {
                resetForm.reset();
                resetForm.action = resetForm.dataset.createAction;
                resetForm.querySelector('[name="id"]').value = '';
                clear(resetForm);
                var resetToggle = resetForm.querySelector('.country-code-manual-toggle');
                if (resetToggle) {
                    resetToggle.checked = false;
                }
                if (window.bindCountryCodeToggles) {
                    window.bindCountryCodeToggles(resetForm);
                }
            }
        }

        if (edit) {
            var form = document.querySelector('#crmLeadModal form');
            var row;
            try {
                row = JSON.parse(edit.dataset.crmEdit);
            } catch (error) {
                return;
            }
            if (form) {
                form.action = form.dataset.updateBase + '/' + row.id;
                var serviceField = form.querySelector('[name="interested_service"]');
                if (serviceField && row.interested_service && !serviceField.querySelector('option[value="' + String(row.interested_service).replace(/"/g, '\\"') + '"]')) {
                    var legacyServiceOption = document.createElement('option');
                    legacyServiceOption.value = row.interested_service;
                    legacyServiceOption.textContent = (row.service_name || row.interested_service) + ' (inactive or legacy)';
                    serviceField.appendChild(legacyServiceOption);
                }
                Object.keys(row).forEach(function (key) {
                    var field = form.querySelector('[name="' + key + '"]');
                    if (field) {
                        field.value = row[key] || '';
                    }
                });
                var editToggle = form.querySelector('.country-code-manual-toggle');
                if (editToggle) {
                    editToggle.checked = !row.telephone_country_code_id && !!row.country_code;
                }
                if (window.bindCountryCodeToggles) {
                    window.bindCountryCodeToggles(form);
                }
                clear(form);
            }
        }
    });

    document.addEventListener('click', function (event) {
        var panelToggle = event.target.closest('.js-crm-followup-panel');
        if (panelToggle) {
            event.preventDefault();
            var panelModal = panelToggle.closest('#crmFollowUpDetailsModal');
            if (panelModal) {
                panelModal.querySelectorAll('[data-followup-panel]').forEach(function (panel) {
                    panel.classList.toggle('d-none', panel.dataset.followupPanel !== panelToggle.dataset.followupPanelTarget);
                });
            }
            return;
        }
        var detailsButton = event.target.closest('.js-crm-followup-details');
        var historyButton = event.target.closest('.js-crm-followup-history');
        if (historyButton) {
            event.preventDefault();
            event.stopPropagation();
            showCrmModal(document.querySelector('#crmFollowUpDetailsModal'), historyButton);
            loadFollowupDetails(historyButton);
            return;
        }
        if (!detailsButton) {
            return;
        }
        showCrmModal(document.querySelector('#crmFollowUpDetailsModal'), detailsButton);
        loadFollowupDetails(detailsButton);
    });

    document.addEventListener('submit', async function (event) {
        var form = event.target.closest('.js-crm-form');
        if (!form) {
            return;
        }
        if (event.defaultPrevented) {
            return false;
        }
        if (!confirmFormAction(form, event)) {
            return false;
        }
        event.preventDefault();
        var button = form.querySelector('button[type="submit"]');
        if (!button || button.disabled) {
            return;
        }
        clear(form);
        if (!form.querySelector('[name="csrf_rtn"]')) {
            var token = document.querySelector('[name="csrf_rtn"]');
            if (token) {
                var hiddenToken = document.createElement('input');
                hiddenToken.type = 'hidden';
                hiddenToken.name = 'csrf_rtn';
                hiddenToken.value = token.value;
                form.appendChild(hiddenToken);
            }
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        if (window.show_loader) {
            window.show_loader();
        }
        var timeout;
        try {
            var controller = new AbortController();
            timeout = window.setTimeout(function () { controller.abort(); }, 30000);
            var response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (form.querySelector('[name="csrf_rtn"]') || {}).value || ''},
                signal: controller.signal
            });
            window.clearTimeout(timeout);
            var contentType = response.headers.get('content-type') || '';
            var payload = contentType.indexOf('application/json') !== -1 ? await response.json() : {status: false, msg: 'The server returned an unexpected response.'};
            if (!response.ok || !payload.status) {
                showErrors(form, payload.errors || {});
                if (window.showError) {
                    window.showError(payload.msg || 'Please correct the highlighted fields.');
                }
                return;
            }
            if (window.showSuccess) {
                window.showSuccess(payload.msg || 'Saved successfully.');
            }
            var workflowModal = form.closest('#crmFollowUpDetailsModal');
            if (workflowModal && workflowModal.dataset.followupId && workflowModal.dataset.followupUrl) {
                form.reset();
                await loadFollowupDetails({dataset: {followupId: workflowModal.dataset.followupId, followupUrl: workflowModal.dataset.followupUrl, followupMode: ''}});
                return;
            }
            if (window.jQuery) {
                window.jQuery(form.closest('.modal')).modal('hide');
            }
            window.setTimeout(function () {
                window.location.reload();
            }, 350);
        } catch (error) {
            if (window.showError) {
                window.showError(error.name === 'AbortError' ? 'The request timed out. Please try again.' : 'The request failed. Please try again.');
            }
        } finally {
            if (timeout) {
                window.clearTimeout(timeout);
            }
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (window.hide_loader) {
                window.hide_loader();
            }
        }
    });
}());
