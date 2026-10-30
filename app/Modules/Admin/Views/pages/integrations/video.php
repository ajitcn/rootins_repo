<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="container-fluid py-4" id="video-integration-page">
    <div class="video-page-heading mb-4">
        <div>
            <div class="video-eyebrow">Integration settings</div>
            <h1 class="video-title mb-2">Video consultation</h1>
            <p class="video-subtitle mb-0">Connect Twilio Video for secure doctor–patient consultation rooms.</p>
        </div>
        <div class="video-security-pill"><i class="fa-solid fa-shield-halved mr-2" aria-hidden="true"></i>Encrypted at rest</div>
    </div>
    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card video-card h-100">
                <div class="video-card-header">
                    <div class="video-card-icon"><i class="fa-solid fa-video" aria-hidden="true"></i></div>
                    <div><h2 class="h5 mb-1">Twilio Video configuration</h2><p class="mb-0">Use a Twilio API Key for server-side access-token generation.</p></div>
                </div>
                <div class="card-body video-card-body">
            <?php if (! ($canManage ?? false)): ?><div class="alert alert-warning mb-0">You do not have permission to manage video configuration.</div><?php else: ?>
            <form id="video-configuration-form" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group col-md-6"><label class="video-label" for="video-account-sid">Twilio Account SID</label><input class="form-control video-input" id="video-account-sid" name="account_sid" type="password" autocomplete="new-password" placeholder="AC…"><small class="form-text">Starts with <code>AC</code>. Leave blank to preserve the saved value.</small></div>
                    <div class="form-group col-md-6"><label class="video-label" for="video-api-key-sid">Twilio API Key SID</label><input class="form-control video-input" id="video-api-key-sid" name="api_key_sid" type="password" autocomplete="new-password" placeholder="SK…"><small class="form-text">Create this under Twilio Console → Video → API keys.</small></div>
                    <div class="form-group col-md-6"><label class="video-label" for="video-api-key-secret">Twilio API Key Secret</label><input class="form-control video-input" id="video-api-key-secret" name="api_key_secret" type="password" autocomplete="new-password" placeholder="Enter secret only when changing it"><small class="form-text">Leave blank to preserve the saved encrypted secret.</small></div>
                    <div class="form-group col-md-6 d-flex align-items-center"><div class="video-enable-box"><div class="custom-control custom-switch"><input class="custom-control-input" id="video-status" name="status" type="checkbox" value="1" <?= ! empty($video['status']) ? 'checked' : '' ?>><label class="custom-control-label video-label" for="video-status">Enable Twilio Video</label></div><small>Disabling stops new video sessions without deleting credentials.</small></div></div>
                </div>
                <div class="video-status-box <?= ! empty($video['configured']) && ! empty($video['status']) ? 'is-ready' : '' ?>"><div class="video-status-dot" aria-hidden="true"></div><div><div class="video-status-label">Current status</div><strong><?= ! empty($video['configured']) && ! empty($video['status']) ? 'Configured and enabled' : 'Not configured or disabled' ?></strong><?php if (! empty($video['updated_at'])): ?><div class="video-status-meta">Last updated: <?= esc($video['updated_at']) ?></div><?php endif; ?></div></div>
                <div class="video-actions"><button class="btn btn-primary px-4" type="submit" id="video-config-save"><i class="fa-solid fa-floppy-disk mr-2" aria-hidden="true"></i>Save configuration</button><button class="btn btn-outline-secondary px-4" type="button" id="video-config-test"><i class="fa-solid fa-circle-check mr-2" aria-hidden="true"></i>Validate configuration</button></div>
            </form>
            <div id="video-config-status" class="alert d-none mt-3" role="status" aria-live="polite"></div>
            <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="video-info-card h-100">
                <div class="video-info-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></div>
                <h2 class="h6">Credential safety</h2>
                <p class="mb-3">These credentials are used only on the server. They are encrypted before storage and never sent to the browser.</p>
                <div class="video-check"><i class="fa-solid fa-check" aria-hidden="true"></i><span>API secret stays private</span></div>
                <div class="video-check"><i class="fa-solid fa-check" aria-hidden="true"></i><span>Credentials are masked after saving</span></div>
                <div class="video-check"><i class="fa-solid fa-check" aria-hidden="true"></i><span>Recording remains separately controlled</span></div>
            </div>
        </div>
    </div>
</main>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
#video-integration-page{max-width:1400px;margin:0 auto;color:#172033}.video-page-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}.video-eyebrow{font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#718096;margin-bottom:6px}.video-title{font-size:1.7rem;font-weight:800;color:#14213d}.video-subtitle{color:#718096}.video-security-pill{background:#eef7f3;border:1px solid #cde8dc;border-radius:999px;color:#287452;font-size:.8rem;font-weight:700;padding:9px 14px;white-space:nowrap}.video-card{border:1px solid #e4e9f0;border-radius:14px;box-shadow:0 8px 24px rgba(24,39,75,.06);overflow:hidden}.video-card-header{display:flex;align-items:center;gap:14px;background:#172033;color:#fff;padding:22px 26px}.video-card-header h2{color:#fff!important;font-weight:700}.video-card-header p{color:#c9d2df!important;font-size:.86rem}.video-card-icon,.video-info-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#2d7ff9;color:#fff;flex:0 0 auto}.video-card-body{padding:28px}.video-label{font-size:.84rem;font-weight:700;color:#263449}.video-input{height:46px;border:1px solid #d7dee8;border-radius:8px}.video-input:focus{border-color:#5594f7;box-shadow:0 0 0 .2rem rgba(45,127,249,.12)}.video-card-body small.form-text{color:#8190a5;line-height:1.45}.video-enable-box{width:100%;padding:16px 18px;border:1px solid #e1e7ef;border-radius:10px;background:#f8fafc}.video-enable-box small{display:block;color:#8190a5;margin:7px 0 0 27px;line-height:1.4}.video-status-box{display:flex;align-items:flex-start;gap:12px;padding:16px 18px;border:1px solid #ead9a7;border-radius:10px;background:#fffaf0;margin:8px 0 22px}.video-status-box.is-ready{border-color:#c9e7d6;background:#f2fbf5}.video-status-dot{width:10px;height:10px;border-radius:50%;background:#d99a17;margin-top:5px}.is-ready .video-status-dot{background:#2ca56a}.video-status-label{font-size:.74rem;text-transform:uppercase;letter-spacing:.08em;color:#8793a5;font-weight:700}.video-status-box strong{display:block;color:#263449;margin-top:2px}.video-status-meta{color:#8793a5;font-size:.78rem;margin-top:5px}.video-actions{display:flex;flex-wrap:wrap;gap:10px}.video-actions .btn{min-height:42px;border-radius:8px;font-weight:700}.video-info-card{padding:28px;border:1px solid #e4e9f0;border-radius:14px;background:#f8fbff;box-shadow:0 8px 24px rgba(24,39,75,.04)}.video-info-card h2{color:#172033;font-weight:800;margin-top:20px}.video-info-card p{color:#718096;line-height:1.6;font-size:.9rem}.video-check{display:flex;align-items:flex-start;gap:10px;color:#34445b;font-size:.85rem;margin-top:14px}.video-check i{color:#2ca56a;margin-top:3px}.video-info-icon{background:#e5f1ff;color:#2d7ff9}@media(max-width:767.98px){.video-page-heading{display:block}.video-security-pill{display:inline-block;margin-top:16px}.video-card-header,.video-card-body,.video-info-card{padding:20px}.video-actions{display:block}.video-actions .btn{width:100%;margin:0 0 10px}.video-actions .btn:last-child{margin-bottom:0}.video-title{font-size:1.45rem}}
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($canManage ?? false): ?><script>
(() => {
    const form = document.getElementById('video-configuration-form');
    const status = document.getElementById('video-config-status');
    const show = (message, ok) => { status.className = `alert mt-3 ${ok ? 'alert-success' : 'alert-danger'}`; status.textContent = message; };
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const button = document.getElementById('video-config-save'); button.disabled = true;
        fetch('<?= base_url('admin/integrations/video/save') ?>', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new FormData(form)})
            .then(response => response.json().then(data => ({response, data})))
            .then(({response, data}) => { show(data.msg || 'Configuration could not be saved.', response.ok && data.status); if (response.ok && data.status) window.setTimeout(() => window.location.reload(), 600); })
            .catch(() => show('Configuration could not be saved safely.', false))
            .finally(() => { button.disabled = false; });
    });
    document.getElementById('video-config-test')?.addEventListener('click', function () {
        const button = this; button.disabled = true;
        fetch('<?= base_url('admin/integrations/video/test') ?>', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':'<?= csrf_hash() ?>'}, body:new URLSearchParams({'<?= csrf_token() ?>':'<?= csrf_hash() ?>'})})
            .then(response => response.json().then(data => ({response, data}))).then(({response, data}) => show(data.msg || 'Validation failed.', response.ok && data.status)).catch(() => show('Configuration validation failed safely.', false)).finally(() => { button.disabled = false; });
    });
})();
</script><?php endif; ?>
<?= $this->endSection() ?>
