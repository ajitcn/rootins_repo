<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-management-standard crm-page access-shell document-templates-page">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Configuration', 'page' => 'Document Templates']) ?>
    <header class="crm-header access-hero mb-4">
        <div><div class="page-eyebrow">Configuration / Documents</div><h1>Document templates</h1><p>Choose the standard layout used when ROOTINS prepares invoices and prescriptions.</p></div>
        <span class="template-status"><i class="bi bi-file-earmark-text mr-1"></i>Document settings</span>
    </header>
    <div id="documentTemplatesAlert" class="mb-3" aria-live="polite"></div>
    <form id="documentTemplatesForm" class="card document-templates-card">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="section-heading"><div><span class="page-eyebrow">Default layouts</span><h2>Invoice and prescription output</h2></div><span class="text-muted small">Changes apply to newly generated documents.</span></div>
            <div class="template-grid">
                <label class="template-option"><span class="template-icon invoice"><i class="bi bi-receipt"></i></span><span class="template-copy"><strong>Invoice</strong><small>Payment and billing documents shown to patients and staff.</small><select class="form-control mt-3" name="invoice_template"><option value="standard" <?= $templates['invoice'] === 'standard' ? 'selected' : '' ?>>Standard</option></select><span class="validation-error" data-error-for="invoice_template"></span></span></label>
                <label class="template-option"><span class="template-icon prescription"><i class="bi bi-prescription2"></i></span><span class="template-copy"><strong>Prescription</strong><small>Clinical prescription documents with patient and medicine details.</small><select class="form-control mt-3" name="prescription_template"><option value="standard" <?= $templates['prescription'] === 'standard' ? 'selected' : '' ?>>Standard</option></select><span class="validation-error" data-error-for="prescription_template"></span></span></label>
            </div>
            <div class="template-note"><i class="bi bi-info-circle mr-2"></i><span>The Standard layout is the currently supported document format. Additional layouts can be added later without changing existing records.</span></div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap"><span class="small text-muted">Existing documents are not modified.</span><button class="btn btn-primary" type="submit"><i class="bi bi-check2 mr-1"></i>Save template settings</button></div>
    </form>
</main>
<?= $this->section('styles') ?><style>
.document-templates-page{max-width:1180px}.document-templates-page .crm-header{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.template-status{border-radius:999px;padding:.5rem .8rem;font-size:.85rem;font-weight:700;white-space:nowrap;background:#eef4ff;color:#2f61a9}.document-templates-card{border:1px solid #e3e8ef;border-radius:14px;box-shadow:0 8px 24px rgba(27,39,58,.05)}.document-templates-card .card-body{padding:1.5rem}.section-heading{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.25rem}.section-heading h2{font-size:1.05rem;margin:.2rem 0 0;color:#182536}.template-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.template-option{display:flex;align-items:flex-start;gap:1rem;margin:0;padding:1.15rem;border:1px solid #e4e9f0;border-radius:12px;background:#fbfcfe}.template-icon{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:11px;font-size:1.2rem}.template-icon.invoice{background:#eaf7f1;color:#18794e}.template-icon.prescription{background:#eef4ff;color:#2f6fce}.template-copy{flex:1}.template-copy strong{display:block;color:#182536;font-size:1rem}.template-copy small{display:block;color:#697586;line-height:1.4;margin-top:.25rem}.template-copy select{max-width:260px}.template-note{display:flex;align-items:flex-start;background:#f5f8fc;border-radius:10px;padding:.75rem 1rem;margin-top:1.25rem;color:#596779;font-size:.9rem}.document-templates-card .card-footer{padding:1rem 1.5rem;background:#fbfcfe;border-top:1px solid #edf0f4}.document-templates-page .validation-error{display:block;color:#c0392b;font-size:.78rem;margin-top:.25rem}@media(max-width:700px){.document-templates-page .crm-header,.section-heading{display:block}.template-status{display:inline-block;margin-top:1rem}.template-grid{grid-template-columns:1fr}.document-templates-card .card-body,.document-templates-card .card-footer{padding:1rem}.document-templates-card .card-footer button{width:100%;margin-top:.75rem}}
</style><?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
$(function(){const f=$('#documentTemplatesForm');f.on('submit',function(e){e.preventDefault();f.find('.validation-error').empty();ajaxRequest({url:'<?= base_url('admin/configuration/settings/document-templates/save') ?>',data:f.serialize(),success:function(r){if(r.status){showSuccess(r.msg);}else{showError(r.msg);if(r.errors)Object.keys(r.errors).forEach(function(k){f.find('[data-error-for="'+k+'"]').text(r.errors[k]);});}}});});});
</script><?= $this->endSection() ?>
<?= $this->endSection() ?>
