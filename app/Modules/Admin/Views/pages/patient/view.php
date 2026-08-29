<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<?php
$patientName = trim(implode(' ', array_filter([
    $patient['first_name'] ?? '',
    $patient['middle_name'] ?? '',
    $patient['last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
?>

<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Patient Management',
        'page'       => 'Patient Details',
    ]) ?>

    <div class="action-btn-container">
        <a href="<?= base_url('admin/patients') ?>" class="btn btn-sm theme-btn">
            <i class="bi bi-arrow-left"></i> Back to Patients
        </a>
        <a href="<?= base_url('admin/patients/' . (int) $patient['id'] . '/medical-history') ?>"
           class="btn btn-sm btn-info">
            <i class="bi bi-file-medical"></i> Medical History
        </a>
        <a href="<?= base_url('admin/patients/' . (int) $patient['id'] . '/documents') ?>"
           class="btn btn-sm btn-secondary">
            <i class="bi bi-file-earmark-text"></i> Documents
        </a>
    </div>

    <?= display_session_msg() ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Patient Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <strong>Registration Number</strong>
                    <div><?= esc($patient['registration_number'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Patient Name</strong>
                    <div><?= esc($patientName) ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Gender</strong>
                    <div><?= esc($patient['gender'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Date of Birth</strong>
                    <div><?= esc($patient['date_of_birth'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Mobile</strong>
                    <div><?= esc(trim(($patient['country_code'] ?? '') . ' ' . ($patient['mobile_number'] ?? ''))) ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Email</strong>
                    <div><?= esc($patient['email'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Clinic</strong>
                    <div><?= esc($patient['clinic_name'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Status</strong>
                    <div>
                        <?php if ((int) ($patient['status'] ?? 0) === 1): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <strong>Address</strong>
                    <div><?= nl2br(esc($patient['address'] ?? '')) ?></div>
                </div>
                <div class="col-md-4 mb-3">
                    <strong>Country</strong>
                    <div><?= esc($patient['country_name'] ?? '') ?></div>
                </div>
                <div class="col-md-4 mb-3">
                    <strong>State</strong>
                    <div><?= esc($patient['state_name'] ?? '') ?></div>
                </div>
                <div class="col-md-4 mb-3">
                    <strong>City</strong>
                    <div><?= esc($patient['city_name'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Postal Code</strong>
                    <div><?= esc($patient['postal_code'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Registered On</strong>
                    <div><?= esc($patient['created_at'] ?? '') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Last Updated</strong>
                    <div><?= esc($patient['updated_at'] ?? '') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><h5 class="mb-0">Clinical Timeline</h5></div>
        <div class="card-body">
            <?php if ($timeline === []): ?>
                <p class="text-muted mb-0">No clinical records found for this patient.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($timeline as $record): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div>
                                    <span class="badge badge-info mr-2"><?= esc($record['type']) ?></span>
                                    <strong><?= esc($record['title']) ?></strong>
                                </div>
                                <small class="text-muted"><?= esc(date('d M Y, h:i A', strtotime((string) $record['occurred_at']))) ?></small>
                            </div>
                            <div class="mt-2 small">
                                <?php if (! empty($record['doctor'])): ?><span class="mr-3"><strong>Doctor:</strong> <?= esc($record['doctor']) ?></span><?php endif; ?>
                                <?php if (! empty($record['appointment_no'])): ?><span class="mr-3"><strong>Appointment:</strong> <?= esc($record['appointment_no']) ?></span><?php endif; ?>
                                <?php if (! empty($record['consultation_no'])): ?><span class="mr-3"><strong>Consultation:</strong> <?= esc($record['consultation_no']) ?></span><?php endif; ?>
                                <?php if (! empty($record['prescription_no'])): ?><span class="mr-3"><strong>Prescription:</strong> <?= esc($record['prescription_no']) ?></span><?php endif; ?>
                            </div>
                            <?php if (! empty($record['summary'])): ?><div class="text-muted mt-1"><?= nl2br(esc($record['summary'])) ?></div><?php endif; ?>
                            <?php if (! empty($record['url']) && $record['can_view']): ?><a href="<?= esc($record['url']) ?>" class="btn btn-sm btn-outline-primary mt-2">View Original Record</a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?= $this->endSection() ?>
