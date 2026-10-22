<?= $this->extend(PATIENT_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $patient['first_name'] ?? '',
    $patient['middle_name'] ?? '',
    $patient['last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$patientName = $patientName !== '' ? $patientName : 'Patient';
$displayDate = static function (?string $value): string {
    return $value !== null && $value !== '' && strtotime($value) !== false
        ? date('d-m-Y', strtotime($value))
        : 'Not recorded';
};
$displayDateTime = static function (?string $value) use ($displayDate): string {
    return $value !== null && $value !== '' && strtotime($value) !== false
        ? $displayDate($value) . ' ' . date('H:i', strtotime($value))
        : 'Not recorded';
};
$label = static fn (string $value): string => ucwords(str_replace('_', ' ', trim($value)));
$badgeClass = static function (string $value): string {
    return match (strtolower($value)) {
        'paid', 'approved', 'confirmed', 'delivered', 'active', 'scheduled' => 'patient-badge-success',
        'failed', 'cancelled', 'rejected', 'returned', 'no_show' => 'patient-badge-danger',
        'pending', 'submitted', 'authorized', 'packed', 'dispatched', 'out_for_delivery', 'clarification_requested' => 'patient-badge-warning',
        default => 'patient-badge-neutral',
    };
};
$mobile = trim(implode(' ', array_filter([
    !empty($patient['telephone_code']) ? '+' . $patient['telephone_code'] : ($patient['country_code'] ?? ''),
    $patient['mobile_number'] ?? '',
])));
$location = trim(implode(', ', array_filter([
    $patient['city_name'] ?? '',
    $patient['state_name'] ?? '',
    $patient['country_name'] ?? '',
])));
?>

<main class="patient-dashboard-shell" aria-labelledby="patient-dashboard-title">
    <form id="patient-dashboard-csrf" class="d-none">
        <?= csrf_field() ?>
    </form>

    <section class="patient-dashboard-hero" aria-label="Patient welcome">
        <div>
            <div class="patient-eyebrow">Patient portal</div>
            <h1 id="patient-dashboard-title">Welcome back, <?= esc($patientName) ?></h1>
            <p>Keep track of your care, appointments, prescriptions, and medicine orders in one secure place.</p>
            <div class="patient-hero-meta">
                <span><i class="bi bi-person-badge mr-1" aria-hidden="true"></i><?= esc($patient['registration_number'] ?? 'Patient number not recorded') ?></span>
                <span><i class="bi bi-building mr-1" aria-hidden="true"></i><?= esc($patient['clinic_name'] ?? 'Clinic not recorded') ?></span>
            </div>
        </div>
        <div class="patient-hero-actions">
            <a class="btn btn-light" href="<?= base_url('patient/appointments') ?>"><i class="bi bi-calendar-plus mr-1" aria-hidden="true"></i>Book appointment</a>
            <a class="btn btn-outline-light" href="<?= base_url('patient/profile') ?>">Update profile</a>
        </div>
    </section>

    <section class="patient-kpi-grid" aria-label="Patient summary">
        <?php foreach ([
            ['Upcoming appointments', $kpis['appointments'] ?? 0, 'bi-calendar-check', 'patient/appointments'],
            ['Pending prescriptions', $kpis['prescriptions'] ?? 0, 'bi-prescription2', 'patient/prescriptions'],
            ['Active medicine orders', $kpis['orders'] ?? 0, 'bi-bag-check', 'patient/orders'],
            ['Pending payments', $kpis['payments'] ?? 0, 'bi-credit-card', 'patient/invoices'],
            ['Unread notifications', $kpis['notifications'] ?? 0, 'bi-bell', 'patient/notifications'],
        ] as [$title, $value, $icon, $url]): ?>
            <a class="patient-kpi-card" href="<?= base_url($url) ?>">
                <span class="patient-kpi-icon"><i class="bi <?= esc($icon) ?>" aria-hidden="true"></i></span>
                <span class="patient-kpi-label"><?= esc($title) ?></span>
                <strong><?= (int) $value ?></strong>
                <span class="patient-kpi-link">View details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
            </a>
        <?php endforeach; ?>
    </section>

    <div class="patient-dashboard-grid patient-dashboard-grid-main">
        <section class="patient-dashboard-card patient-profile-summary" aria-labelledby="patient-profile-summary-title">
            <div class="patient-card-heading">
                <div>
                    <div class="patient-eyebrow">Your information</div>
                    <h2 id="patient-profile-summary-title">Profile summary</h2>
                </div>
                <a class="patient-text-link" href="<?= base_url('patient/profile') ?>">Edit profile</a>
            </div>
            <div class="patient-profile-heading">
                <span class="patient-avatar" aria-hidden="true"><?= esc(strtoupper(substr($patientName, 0, 1))) ?></span>
                <div>
                    <strong><?= esc($patientName) ?></strong>
                    <span><?= esc($patient['registration_number'] ?? 'Patient number not recorded') ?></span>
                </div>
            </div>
            <dl class="patient-detail-list">
                <div><dt>Mobile</dt><dd><?= esc($mobile !== '' ? $mobile : 'Not recorded') ?></dd></div>
                <div><dt>Email</dt><dd><?= esc($patient['email'] ?? 'Not recorded') ?></dd></div>
                <div><dt>Gender / date of birth</dt><dd><?= esc($label((string) ($patient['gender'] ?? 'Not recorded'))) ?> · <?= esc($displayDate($patient['date_of_birth'] ?? null)) ?></dd></div>
                <div><dt>Location</dt><dd><?= esc($location !== '' ? $location : ($patient['address'] ?? 'Not recorded')) ?></dd></div>
            </dl>
        </section>

        <section class="patient-dashboard-card" aria-labelledby="patient-quick-actions-title">
            <div class="patient-card-heading">
                <div>
                    <div class="patient-eyebrow">Shortcuts</div>
                    <h2 id="patient-quick-actions-title">Quick actions</h2>
                </div>
            </div>
            <div class="patient-quick-actions">
                <a href="<?= base_url('patient/appointments') ?>"><i class="bi bi-calendar2-plus" aria-hidden="true"></i><span>Book appointment</span></a>
                <a href="<?= base_url('patient/prescriptions') ?>"><i class="bi bi-prescription2" aria-hidden="true"></i><span>View prescriptions</span></a>
                <a href="<?= base_url('patient/orders') ?>"><i class="bi bi-box-seam" aria-hidden="true"></i><span>View orders</span></a>
                <a href="<?= base_url('patient/invoices') ?>"><i class="bi bi-receipt" aria-hidden="true"></i><span>View invoices</span></a>
                <a href="<?= base_url('patient/profile') ?>"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Update profile</span></a>
            </div>
        </section>
    </div>

    <div class="patient-dashboard-grid patient-dashboard-grid-content">
        <section class="patient-dashboard-card" aria-labelledby="patient-appointments-title">
            <div class="patient-card-heading">
                <div><div class="patient-eyebrow">Next in your care</div><h2 id="patient-appointments-title">Upcoming appointments</h2></div>
                <a class="patient-text-link" href="<?= base_url('patient/appointments') ?>">View all</a>
            </div>
            <?php if ($appointments === []): ?>
                <div class="patient-empty-state"><i class="bi bi-calendar-x" aria-hidden="true"></i><p>No upcoming appointments.</p><a href="<?= base_url('patient/appointments') ?>">Book an appointment</a></div>
            <?php else: ?>
                <div class="patient-dashboard-list">
                    <?php foreach ($appointments as $appointment): ?>
                        <article class="patient-dashboard-row">
                            <div class="patient-row-date"><strong><?= esc($displayDate($appointment['appointment_date'] ?? null)) ?></strong><span><?= esc(rootins_format_time($appointment['appointment_time'] ?? '')) ?></span></div>
                            <div class="patient-row-content"><strong><?= esc(trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))) ?></strong><span><?= esc($appointment['service_name'] ?? $label((string) ($appointment['appointment_type'] ?? 'Consultation'))) ?></span></div>
                            <span class="patient-status-badge <?= esc($badgeClass((string) ($appointment['status'] ?? ''))) ?>"><?= esc($label((string) ($appointment['status'] ?? 'Not recorded'))) ?></span>
                            <a class="patient-row-action" href="<?= base_url('patient/appointments') ?>" aria-label="View appointment details"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="patient-dashboard-card" aria-labelledby="patient-prescriptions-title">
            <div class="patient-card-heading">
                <div><div class="patient-eyebrow">Medication plan</div><h2 id="patient-prescriptions-title">Recent prescriptions</h2></div>
                <a class="patient-text-link" href="<?= base_url('patient/prescriptions') ?>">View all</a>
            </div>
            <?php if ($prescriptions === []): ?>
                <div class="patient-empty-state"><i class="bi bi-prescription2" aria-hidden="true"></i><p>No prescriptions found.</p></div>
            <?php else: ?>
                <div class="patient-dashboard-list">
                    <?php foreach ($prescriptions as $prescription): ?>
                        <article class="patient-dashboard-row">
                            <div class="patient-row-icon"><i class="bi bi-prescription2" aria-hidden="true"></i></div>
                            <div class="patient-row-content"><strong><?= esc($prescription['prescription_number'] ?? 'Prescription') ?></strong><span><?= esc($displayDate($prescription['prescription_date'] ?? null)) ?> · <?= (int) ($prescription['medicine_count'] ?? 0) ?> medicines</span><small>Dr. <?= esc(trim(($prescription['doctor_first_name'] ?? '') . ' ' . ($prescription['doctor_last_name'] ?? ''))) ?></small></div>
                            <span class="patient-status-badge <?= esc($badgeClass((string) ($prescription['status'] ?? ''))) ?>"><?= esc($label((string) ($prescription['status'] ?? 'Not recorded'))) ?></span>
                            <a class="patient-row-action" href="<?= base_url('patient/prescriptions') ?>" aria-label="View prescription"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="patient-dashboard-grid patient-dashboard-grid-content">
        <section class="patient-dashboard-card" aria-labelledby="patient-orders-title">
            <div class="patient-card-heading">
                <div><div class="patient-eyebrow">Fulfilment</div><h2 id="patient-orders-title">Recent medicine orders</h2></div>
                <a class="patient-text-link" href="<?= base_url('patient/orders') ?>">View all</a>
            </div>
            <?php if ($orders === []): ?>
                <div class="patient-empty-state"><i class="bi bi-bag" aria-hidden="true"></i><p>No medicine orders found.</p></div>
            <?php else: ?>
                <div class="patient-dashboard-list">
                    <?php foreach ($orders as $order): ?>
                        <article class="patient-dashboard-row patient-order-row">
                            <div class="patient-row-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
                            <div class="patient-row-content"><strong><?= esc($order['order_number'] ?? 'Order') ?></strong><span><?= esc($displayDate($order['created_at'] ?? null)) ?> · <?= (int) ($order['medicine_count'] ?? 0) ?> medicines</span><small><?= esc($order['tracking_reference'] ?? 'Tracking not available') ?></small></div>
                            <div class="patient-row-status-stack"><span class="patient-status-badge <?= esc($badgeClass((string) ($order['payment_status'] ?? ''))) ?>">Payment: <?= esc($label((string) ($order['payment_status'] ?? ''))) ?></span><span class="patient-status-badge <?= esc($badgeClass((string) ($order['delivery_status'] ?? ''))) ?>">Delivery: <?= esc($label((string) ($order['delivery_status'] ?? ''))) ?></span></div>
                            <a class="patient-row-action" href="<?= base_url('patient/orders') ?>" aria-label="View order"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="patient-dashboard-card" aria-labelledby="patient-invoices-title">
            <div class="patient-card-heading">
                <div><div class="patient-eyebrow">Payments</div><h2 id="patient-invoices-title">Recent invoices</h2></div>
                <a class="patient-text-link" href="<?= base_url('patient/invoices') ?>">View all</a>
            </div>
            <?php if ($invoices === []): ?>
                <div class="patient-empty-state"><i class="bi bi-receipt" aria-hidden="true"></i><p>No invoices found.</p></div>
            <?php else: ?>
                <div class="patient-dashboard-list">
                    <?php foreach ($invoices as $invoice): ?>
                        <article class="patient-dashboard-row">
                            <div class="patient-row-icon"><i class="bi bi-receipt" aria-hidden="true"></i></div>
                            <div class="patient-row-content"><strong><?= esc($invoice['invoice_number'] ?? 'Invoice') ?></strong><span><?= esc($displayDate($invoice['created_at'] ?? null)) ?> · <?= esc(($invoice['currency'] ?? 'INR') . ' ' . number_format((float) ($invoice['total'] ?? 0), 2)) ?></span></div>
                            <span class="patient-status-badge <?= esc($badgeClass((string) ($invoice['payment_status'] ?? ''))) ?>"><?= esc($label((string) ($invoice['payment_status'] ?? 'Not recorded'))) ?></span>
                            <a class="patient-row-action" href="<?= base_url('patient/invoices') ?>" aria-label="View invoice"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="patient-dashboard-card" aria-labelledby="patient-notifications-title">
        <div class="patient-card-heading">
            <div><div class="patient-eyebrow">Stay informed</div><h2 id="patient-notifications-title">Notifications</h2></div>
            <a class="patient-text-link" href="<?= base_url('patient/notifications') ?>">View all</a>
        </div>
        <?php if ($notifications === []): ?>
            <div class="patient-empty-state patient-empty-state-inline"><i class="bi bi-bell-slash" aria-hidden="true"></i><p>No notifications yet.</p></div>
        <?php else: ?>
            <div class="patient-notification-preview">
                <?php foreach ($notifications as $notification): ?>
                    <article class="patient-notification-item <?= ($notification['status'] ?? '') === 'unread' ? 'is-unread' : '' ?>">
                        <span class="patient-notification-dot" aria-hidden="true"></span>
                        <div><strong><?= esc($notification['title'] ?? 'Notification') ?></strong><p><?= esc($notification['message'] ?? '') ?></p><small><?= esc($displayDateTime($notification['created_at'] ?? null)) ?></small></div>
                        <?php if (($notification['status'] ?? '') === 'unread'): ?><button class="btn btn-sm btn-outline-primary patient-mark-read" type="button" data-notification-id="<?= (int) ($notification['id'] ?? 0) ?>">Mark read</button><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="patient-dashboard-card patient-dashboard-visits-card" aria-labelledby="patient-visits-title">
        <div class="patient-card-heading">
            <div><div class="patient-eyebrow">Care history</div><h2 id="patient-visits-title">Recent visits</h2></div>
            <a class="patient-text-link" href="<?= base_url('patient/visits') ?>">View all</a>
        </div>
        <?php if (($visits ?? []) === []): ?>
            <div class="patient-empty-state patient-empty-state-inline"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i><p>No visit history found.</p></div>
        <?php else: ?>
            <div class="patient-dashboard-list">
                <?php foreach ($visits as $visit): ?>
                    <article class="patient-dashboard-row">
                        <div class="patient-row-icon"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></div>
                        <div class="patient-row-content"><strong><?= esc($visit['consultation_number'] ?? 'Visit') ?></strong><span><?= esc($displayDate($visit['consultation_date'] ?? null)) ?> · <?= esc(substr((string) ($visit['consultation_time'] ?? ''), 0, 5) ?: 'Time not recorded') ?></span><small>Dr. <?= esc(trim(($visit['doctor_first_name'] ?? '') . ' ' . ($visit['doctor_last_name'] ?? ''))) ?> · <?= esc($visit['service_name'] ?? 'Consultation') ?></small></div>
                        <span class="patient-status-badge <?= esc($badgeClass((string) ($visit['status'] ?? ''))) ?>"><?= esc($label((string) ($visit['status'] ?? 'Not recorded'))) ?></span>
                        <a class="patient-row-action" href="<?= base_url('patient/visits') ?>" aria-label="View visit history"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        $('.patient-mark-read').on('click', function () {
            var button = $(this);
            var notificationId = button.data('notification-id');
            var csrf = $('#patient-dashboard-csrf').serialize();

            button.prop('disabled', true);
            ajaxRequest({
                url: '<?= base_url('patient/notifications') ?>/' + notificationId + '/read',
                method: 'POST',
                data: csrf,
                success: function (response) {
                    if (response.status) {
                        button.closest('.patient-notification-item').removeClass('is-unread');
                        button.remove();
                    } else {
                        button.prop('disabled', false);
                        showError(response.msg);
                    }
                },
                error: function () {
                    button.prop('disabled', false);
                },
            });
        });
    });
</script>
<?= $this->endSection() ?>
