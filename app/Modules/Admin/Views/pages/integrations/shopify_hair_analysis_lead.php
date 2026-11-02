<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<?php
$result = json_decode((string) ($lead['result_json'] ?? ''), true);
$result = is_array($result) ? $result : [];
$questionnaire = json_decode((string) ($lead['questionnaire_json'] ?? ''), true);
$questionnaire = is_array($questionnaire) ? $questionnaire : [];
$status = (string) ($lead['status'] ?? 'unknown');
$statusClass = $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'warning');
$label = static fn(string $value): string => ucwords(str_replace('_', ' ', $value));
$value = static fn($item): string => is_scalar($item) && (string) $item !== '' ? (string) $item : '—';
$zones = ['hairline_left', 'hairline_center', 'hairline_right', 'front_area', 'mid_scalp', 'crown_area'];
$reportStage = $result['hair_loss_stage'] ?? $result['norwood_stage'] ?? $result['norwood_scale'] ?? $result['pattern_scale'] ?? $result['hair_loss_pattern'] ?? null;
$reportGrafts = $result['estimated_grafts_required'] ?? $result['graft_estimate'] ?? $result['estimated_grafts'] ?? $result['grafts_required'] ?? $result['recommended_grafts'] ?? null;
if (is_array($reportGrafts)) {
    $reportGrafts = $reportGrafts['total'] ?? $reportGrafts['estimated_total'] ?? $reportGrafts['range'] ?? $reportGrafts;
}
$knownReportFields = ['hair_loss_stage', 'confidence', 'zone_visible', 'hair_condition', 'severity_score', 'estimated_grafts_required', 'donor_area_quality', 'overall_coverage_needed', 'summary', 'next_step_advice', 'photo_quality', 'key_findings'];
$knownReportFields = array_merge($knownReportFields, ['norwood_stage', 'norwood_scale', 'pattern_scale', 'hair_loss_pattern', 'graft_estimate', 'estimated_grafts', 'grafts_required', 'recommended_grafts']);
$renderReportValue = null;
$renderReportValue = static function ($item, int $depth = 0) use (&$renderReportValue, $label, $value): string {
    if (is_bool($item)) {
        return $item ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-light">No</span>';
    }
    if (is_scalar($item)) {
        return '<span class="report-value">' . esc($value($item)) . '</span>';
    }
    if (!is_array($item) || $item === []) {
        return '<span class="text-muted">—</span>';
    }
    $isList = array_keys($item) === range(0, count($item) - 1);
    if ($isList) {
        $html = '<ul class="report-list mb-0">';
        foreach ($item as $entry) {
            $html .= '<li>' . $renderReportValue($entry, $depth + 1) . '</li>';
        }
        return $html . '</ul>';
    }
    $html = '<div class="report-nested">';
    foreach ($item as $key => $entry) {
        $html .= '<div class="report-nested-row"><div class="report-nested-label">' . esc($label((string) $key)) . '</div><div>' . $renderReportValue($entry, $depth + 1) . '</div></div>';
    }
    return $html . '</div>';
};
?>
<style>
    .hair-detail .card { border: 1px solid #e3e8ef; border-radius: 14px; }
    .hair-detail .card-header { background: #f8fafc; border-bottom: 1px solid #e3e8ef; }
    .hair-detail .metric { min-height: 86px; padding: 16px; border: 1px solid #e3e8ef; border-radius: 12px; background: #fff; }
    .hair-detail .metric-label { color: #718096; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .hair-detail .metric-value { color: #172033; font-size: 1rem; font-weight: 700; margin-top: 5px; word-break: break-word; }
    .hair-detail .report-copy { white-space: pre-line; line-height: 1.65; color: #334155; }
    .hair-detail .photo-tile { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 12px; overflow: hidden; }
    .hair-detail .photo-tile img { display: block; width: 100%; height: 190px; object-fit: cover; }
    .hair-detail .photo-empty { height: 190px; display: grid; place-items: center; color: #718096; }
    .hair-detail .table th { color: #64748b; font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
    .hair-detail .table td { vertical-align: middle; }
    .hair-detail .report-list { padding-left: 1.25rem; color: #334155; }
    .hair-detail .report-list li { margin-bottom: .45rem; }
    .hair-detail .report-nested { display: grid; gap: .65rem; }
    .hair-detail .report-nested-row { display: grid; grid-template-columns: minmax(150px, 30%) 1fr; gap: 1rem; padding: .7rem .8rem; border: 1px solid #e8edf3; border-radius: 9px; background: #fbfcfe; }
    .hair-detail .report-nested-label { color: #64748b; font-size: .76rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .hair-detail .report-value { white-space: pre-line; line-height: 1.55; }
    .hair-detail .quality-box { border-left: 4px solid #3b82f6; background: #f8fbff; border-radius: 8px; padding: 1rem; }
    .hair-detail .finding-card { height: 100%; border: 1px solid #e8edf3; border-radius: 10px; padding: .9rem; background: #fbfcfe; }
    .hair-detail .finding-card .finding-title { color: #475569; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    @media (max-width: 575.98px) { .hair-detail .report-nested-row { grid-template-columns: 1fr; gap: .25rem; } }
    .hair-detail pre { max-height: 420px; overflow: auto; white-space: pre-wrap; word-break: break-word; background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 16px; font-size: .82rem; }
</style>
<main class="dashboard-container hair-detail" aria-labelledby="hair-analysis-lead-title">
    <div class="page-heading d-flex justify-content-between align-items-start flex-wrap mb-4">
        <div><div class="page-eyebrow">Shopify / AI Hair Analysis</div><h1 id="hair-analysis-lead-title" class="h3 mb-1">Analysis <?= esc((string) $lead['lead_reference']) ?></h1><p class="text-muted mb-0">Review the customer profile, uploaded photographs, AI result, and ROOTINS linkage.</p></div>
        <a class="btn btn-outline-primary mt-2 mt-md-0" href="<?= base_url('admin/integrations/shopify/ai-hair-analysis/leads') ?>"><i class="bi bi-arrow-left mr-1"></i> Back to analyses</a>
    </div>
    <section class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-start flex-wrap"><div><div class="text-muted small text-uppercase font-weight-bold">Analysis reference</div><div class="h4 mb-1"><?= esc((string) $lead['lead_reference']) ?></div><div class="text-muted">Created <?= esc((string) ($lead['created_at'] ?? '—')) ?></div></div><span class="badge badge-<?= $statusClass ?> px-3 py-2 mt-2 mt-md-0"><?= esc($label($status)) ?></span></div><hr><div class="row"><div class="col-md-4 mb-3 mb-md-0"><div class="metric"><div class="metric-label">Customer</div><div class="metric-value"><?= esc(trim((string) ($lead['first_name'] ?? '') . ' ' . (string) ($lead['last_name'] ?? ''))) ?></div><small class="text-muted">Shopify ID <?= esc((string) ($lead['shopify_customer_id'] ?? '—')) ?></small></div></div><div class="col-md-4 mb-3 mb-md-0"><div class="metric"><div class="metric-label">Contact</div><div class="metric-value"><?= esc((string) ($lead['mobile_number'] ?? '—')) ?></div><small class="text-muted"><?= esc((string) ($lead['email'] ?? '—')) ?></small></div></div><div class="col-md-4"><div class="metric"><div class="metric-label">Store / CRM</div><div class="metric-value small"><?= esc((string) ($lead['store_domain'] ?? '—')) ?></div><small class="text-muted">CRM lead ID: <?= esc((string) ($lead['crm_lead_id'] ?? 'Not linked')) ?></small></div></div></div></div></section>
    <div class="row"><div class="col-xl-7 mb-4"><section class="card shadow-sm h-100"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Uploaded photographs</h2><span class="badge badge-light"><?= count($lead['images'] ?? []) ?> image(s)</span></div><div class="card-body"><div class="row"><?php foreach (($lead['images'] ?? []) as $image): ?><div class="col-sm-6 col-lg-4 mb-3"><div class="photo-tile h-100"><?php if (!empty($image['temporary_url'])): ?><img src="<?= esc($image['temporary_url']) ?>" alt="Hair analysis <?= esc((string) ($image['angle'] ?? 'photo')) ?>" loading="lazy"><?php else: ?><div class="photo-empty">Preview unavailable</div><?php endif; ?><div class="p-2 small font-weight-bold text-muted"><?= esc(ucfirst((string) ($image['angle'] ?? 'photo'))) ?></div></div></div><?php endforeach; ?><?php if (empty($lead['images'])): ?><div class="col-12"><div class="alert alert-light mb-0">No uploaded photographs are attached to this analysis.</div></div><?php endif; ?></div></div></section></div>
        <div class="col-xl-5 mb-4"><section class="card shadow-sm mb-4"><div class="card-header"><h2 class="h5 mb-0">Patient / lead linkage</h2></div><div class="card-body"><?php if (!empty($lead['patient_reference'])): ?><div class="alert alert-success mb-0"><i class="bi bi-check-circle mr-1"></i> Linked to ROOTINS patient <strong><?= esc($lead['patient_reference']) ?></strong>.</div><?php else: ?><p class="text-muted small">This is a global lead. Link an existing patient or create one after confirming the contact details.</p><form id="hair-analysis-patient-link"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><div class="input-group"><input class="form-control" name="patient_reference" placeholder="PAT-000001" required><div class="input-group-append"><button class="btn btn-outline-primary" type="submit">Link</button></div></div></form><?php endif; ?></div></section><?php if (empty($lead['patient_reference'])): ?><section class="card shadow-sm"><div class="card-header"><h2 class="h5 mb-0">Create global patient</h2></div><div class="card-body"><form id="hair-analysis-patient-create"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><div class="form-row"><div class="form-group col-5"><label>Country code</label><input class="form-control" name="country_code" placeholder="+91" required></div><div class="form-group col-7"><label>Mobile number</label><input class="form-control" name="mobile_number" value="<?= esc((string) ($lead['mobile_number'] ?? '')) ?>" required></div></div><div class="form-group"><label>Date of birth</label><input class="form-control" name="date_of_birth" type="date" required></div><div class="form-group"><label>Email <span class="text-muted">(optional)</span></label><input class="form-control" name="email" type="email" value="<?= esc((string) ($lead['email'] ?? '')) ?>"></div><button class="btn btn-primary btn-block" type="submit">Create patient</button></form></div></section><?php endif; ?></div></div>
    <?php if ($status === 'completed'): ?><section class="card shadow-sm mb-4"><div class="card-header"><h2 class="h5 mb-0">AI assessment</h2></div><div class="card-body"><div class="row mb-3"><div class="col-md-4"><div class="metric"><div class="metric-label">Hair-loss stage</div><div class="metric-value"><?= is_array($reportStage) ? $renderReportValue($reportStage) : esc($value($reportStage)) ?></div></div></div><div class="col-md-4 mt-3 mt-md-0"><div class="metric"><div class="metric-label">Confidence</div><div class="metric-value"><?= esc($value($result['confidence'] ?? null)) ?></div></div></div><div class="col-md-4 mt-3 mt-md-0"><div class="metric"><div class="metric-label">Estimated grafts</div><div class="metric-value"><?= is_array($reportGrafts) ? $renderReportValue($reportGrafts) : esc($value($reportGrafts)) ?></div></div></div></div><?php foreach ([['summary','Summary'],['overall_coverage_needed','Overall coverage'],['donor_area_quality','Donor area'],['next_step_advice','Next step']] as [$key,$title]): if (!empty($result[$key])): ?><div class="mb-3"><h3 class="h6 text-primary mb-2"><?= esc($title) ?></h3><div class="report-copy"><?= esc($value($result[$key])) ?></div></div><?php endif; endforeach; ?><?php if (!empty($result['photo_quality']) && is_array($result['photo_quality'])): ?><div class="quality-box mb-4"><h3 class="h6 mb-2"><i class="bi bi-camera mr-1"></i>Photo quality</h3><div class="mb-2"><?= $renderReportValue($result['photo_quality']) ?></div></div><?php endif; ?><?php if (!empty($result['key_findings']) && is_array($result['key_findings'])): ?><div class="mb-4"><h3 class="h6 text-primary mb-2">Key findings</h3><?= $renderReportValue($result['key_findings']) ?></div><?php endif; ?><h3 class="h6 text-primary mt-4">Zone assessment</h3><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Zone</th><th>Visible</th><th>Condition</th><th>Severity</th><th>Grafts</th></tr></thead><tbody><?php foreach ($zones as $zone): ?><tr><td class="font-weight-bold"><?= esc($label($zone)) ?></td><td><?= !empty($result['zone_visible'][$zone]) ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-light">No</span>' ?></td><td><?= esc($value($result['hair_condition'][$zone] ?? null)) ?></td><td><?= esc($value($result['severity_score'][$zone] ?? null)) ?></td><td><?= esc($value($result['estimated_grafts_required'][$zone] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php $additionalFields = array_diff_key($result, array_flip($knownReportFields)); if ($additionalFields !== []): ?><hr class="my-4"><h3 class="h6 text-primary mb-3">Additional assessment details</h3><?= $renderReportValue($additionalFields) ?><?php endif; ?></div></section><?php elseif ($status === 'failed'): ?><div class="alert alert-danger d-flex justify-content-between align-items-center"><span>Analysis failed: <?= esc((string) ($lead['error_code'] ?? 'Unknown error')) ?></span><button type="button" class="btn btn-sm btn-outline-danger" id="hair-analysis-retry">Retry analysis</button></div><?php else: ?><div class="alert alert-info">Analysis is still processing.</div><?php endif; ?>
    <section class="card shadow-sm mb-4"><div class="card-header"><h2 class="h5 mb-0">Submitted questionnaire</h2></div><div class="card-body"><div class="row"><?php foreach ($questionnaire as $key => $item): if (is_scalar($item) && (string) $item !== ''): ?><div class="col-md-6 mb-3"><div class="metric-label"><?= esc($label((string) $key)) ?></div><div><?= esc((string) $item) ?></div></div><?php endif; endforeach; ?><?php if ($questionnaire === []): ?><div class="col-12 text-muted">No questionnaire data recorded.</div><?php endif; ?></div></div></section>
    <?php if ($status === 'completed'): ?><details class="mb-4"><summary class="text-muted small">Technical JSON (for troubleshooting)</summary><pre class="mt-2"><?= esc(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details><?php endif; ?>
</main>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
$(function () {
    function submitForm(selector, url, errorMessage) { $(selector).on('submit', function (event) { event.preventDefault(); var form = $(this), button = form.find('button[type="submit"]'); button.prop('disabled', true); $.ajax({ url: url, method: 'POST', data: form.serialize(), headers: { 'X-Requested-With': 'XMLHttpRequest' }, success: function (response) { if (response.status) window.location.reload(); else window.alert(response.msg || errorMessage); }, error: function (xhr) { var response = xhr.responseJSON || {}; window.alert(response.msg || errorMessage); }, complete: function () { button.prop('disabled', false); } }); }); }
    submitForm('#hair-analysis-patient-link', '<?= base_url('admin/integrations/shopify/ai-hair-analysis/leads/' . (int) $lead['id'] . '/link-patient') ?>', 'The patient could not be linked safely.');
    submitForm('#hair-analysis-patient-create', '<?= base_url('admin/integrations/shopify/ai-hair-analysis/leads/' . (int) $lead['id'] . '/create-patient') ?>', 'The patient could not be created safely.');
    $('#hair-analysis-retry').on('click', function () { var button = $(this); button.prop('disabled', true).text('Retrying…'); $.ajax({ url: '<?= base_url('admin/integrations/shopify/ai-hair-analysis/leads/' . (int) $lead['id'] . '/retry') ?>', method: 'POST', data: { '<?= csrf_token() ?>': '<?= csrf_hash() ?>' }, headers: { 'X-Requested-With': 'XMLHttpRequest' }, success: function (response) { if (response.status) window.location.reload(); else window.alert(response.msg || 'Retry failed.'); }, error: function (xhr) { var response = xhr.responseJSON || {}; window.alert(response.msg || 'Retry failed safely.'); }, complete: function () { button.prop('disabled', false).text('Retry analysis'); } }); });
});
</script><?= $this->endSection() ?>
