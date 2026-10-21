<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="doctor-profile-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Doctor Management', 'page' => 'Doctor Profile']) ?>
    <h1 id="doctor-profile-title" class="sr-only">Doctor Profile</h1>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div><h2 class="h4 mb-1">Dr. <?= esc(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''))) ?></h2><p class="text-muted mb-0"><?= esc($doctor['doctor_code'] ?? '') ?> · <?= esc($doctor['clinic_name'] ?? '') ?></p></div>
        <a class="btn btn-outline-secondary" href="<?= base_url('admin/doctors') ?>"><i class="bi bi-arrow-left mr-1" aria-hidden="true"></i> Back to doctors</a>
    </div>
    <div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Specialization</dt><dd class="col-sm-9"><?= esc($doctor['specializations'] ?? '—') ?></dd><dt class="col-sm-3">Qualification</dt><dd class="col-sm-9"><?= esc($doctor['qualification'] ?? '—') ?></dd><dt class="col-sm-3">Registration number</dt><dd class="col-sm-9"><?= esc($doctor['registration_number'] ?? '—') ?></dd><dt class="col-sm-3">Consultation duration</dt><dd class="col-sm-9"><?= esc((string) ($doctor['consultation_duration'] ?? '')) ?> minutes</dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge badge-<?= (int) ($doctor['status'] ?? 0) === 1 ? 'success' : 'danger' ?>"><?= (int) ($doctor['status'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></dd></dl></div></div>
    <div class="card mt-3"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Languages</dt><dd class="col-sm-9"><?= esc($doctor['languages'] ?? '—') ?></dd><dt class="col-sm-3">Popular treatments</dt><dd class="col-sm-9"><?= esc($doctor['popular_treatments'] ?? '—') ?></dd></dl></div></div>
</main>
<?= $this->endSection() ?>
