<?php
$appointment = $appointment ?? [];
$session = $session ?? null;
$configured = (bool) ($configured ?? false);
$provider = trim((string) ($provider ?? 'provider boundary')) ?: 'provider boundary';
$appointmentId = (int) ($appointment['id'] ?? 0);
$patientName = trim((string) ($appointment['patient_first_name'] ?? '') . ' ' . (string) ($appointment['patient_last_name'] ?? '')) ?: 'Unknown patient';
$patientNumber = trim((string) ($appointment['registration_number'] ?? '')) ?: '—';
$mobile = trim((string) ($appointment['patient_mobile_number'] ?? '')) ?: '—';
$queue = $appointment['queue_number'] ?? ($appointment['queue_position'] ?? '—');
$status = $session['status'] ?? 'not_started';
$statusLabels = ['not_started' => 'Not started', 'waiting' => 'Waiting', 'active' => 'Active', 'ended' => 'Ended', 'failed' => 'Failed'];
$statusLabel = $statusLabels[$status] ?? 'Not started';
$canOperate = in_array($status, ['waiting', 'active'], true);
?>
<div class="doctor-video-panel" data-appointment-id="<?= $appointmentId ?>">
    <header class="doctor-video-panel-header">
        <div class="doctor-video-title-wrap">
            <span class="doctor-video-eyebrow"><i class="bi bi-shield-lock mr-1" aria-hidden="true"></i>Appointment-linked room</span>
            <h3 class="doctor-video-panel-title">Video Consultation</h3>
            <p class="doctor-video-panel-subtitle mb-0">
                <?= esc($appointment['appointment_number'] ?? ('Appointment #' . $appointmentId)) ?>
                <span aria-hidden="true">·</span><?= esc($patientName) ?>
            </p>
        </div>
        <span class="doctor-video-status-badge doctor-video-status-<?= esc($status) ?>">
            <span class="doctor-video-status-dot" aria-hidden="true"></span>
            <span class="doctor-video-status"><?= esc($statusLabel) ?></span>
        </span>
    </header>

    <div class="doctor-video-context" aria-label="Appointment and patient summary">
        <div class="doctor-video-context-primary">
            <span class="doctor-video-avatar" aria-hidden="true"><?= esc(strtoupper(substr($patientName, 0, 1))) ?></span>
            <div class="doctor-video-context-copy">
                <strong><?= esc($patientName) ?></strong>
                <span><?= esc($patientNumber) ?> <span aria-hidden="true">·</span> <?= esc($mobile) ?></span>
            </div>
        </div>
                <div class="doctor-video-context-item"><span>Date / time</span><strong><?= esc(rootins_format_date($appointment['appointment_date'] ?? '') ?: '—') ?> · <?= esc(rootins_format_time($appointment['appointment_time'] ?? '') ?: '—') ?></strong></div>
        <div class="doctor-video-context-item"><span>Service / queue</span><strong><?= esc($appointment['service_name'] ?? '—') ?> · Queue <?= esc((string) $queue) ?></strong></div>
    </div>

    <?php if (! $configured): ?>
        <div class="doctor-video-provider-alert" role="status">
            <i class="bi bi-info-circle mr-2" aria-hidden="true"></i>
            <div><strong>Video provider setup is pending</strong><span>The secure ROOTINS session is ready, but <?= esc($provider) ?> is not configured in this environment. No credentials or secrets are exposed here.</span></div>
        </div>
    <?php endif; ?>

    <section class="doctor-video-stage <?= $status === 'active' ? 'is-connected' : '' ?>" aria-label="Video connection area">
        <div class="doctor-video-stage-orb"><i class="bi bi-camera-video" aria-hidden="true"></i></div>
        <strong class="doctor-video-stage-title"><?= $status === 'active' ? 'Connected' : ($status === 'failed' ? 'Connection failed' : ($status === 'ended' ? 'Consultation ended' : 'Ready when you are')) ?></strong>
        <p class="doctor-video-connection mb-0"><?= $status === 'active' ? 'You are connected to the secure consultation room.' : ($status === 'failed' ? 'The provider could not establish a connection. You can rejoin when the provider is available.' : ($status === 'ended' ? 'This consultation has ended.' : 'Start the consultation to prepare the secure room, then join when the provider is ready.')) ?></p>
        <div class="doctor-video-media-grid"><div class="doctor-video-local" data-video-local></div><div class="doctor-video-remote" data-video-remote></div></div>
        <span class="doctor-video-provider-chip"><i class="bi bi-lock-fill mr-1" aria-hidden="true"></i>Private appointment session</span>
    </section>

    <div class="doctor-video-controls" role="group" aria-label="Video controls">
        <?php if ($status === 'not_started' || $status === 'failed'): ?>
            <button type="button" class="btn btn-primary js-video-start" data-start-url="<?= base_url('doctor/video/' . $appointmentId . '/start') ?>" title="Start consultation"><i class="bi bi-play-circle mr-1" aria-hidden="true"></i><span>Start Consultation</span></button>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-primary js-video-join" data-token-url="<?= base_url('doctor/video/' . $appointmentId . '/token') ?>" aria-label="<?= $status === 'active' ? 'Rejoin consultation' : 'Join consultation' ?>" <?= ! $canOperate ? 'disabled' : '' ?> title="<?= $status === 'active' ? 'Rejoin consultation' : 'Join consultation' ?>"><i class="bi bi-box-arrow-in-right mr-1" aria-hidden="true"></i><span><?= $status === 'active' ? 'Rejoin' : 'Join' ?></span></button>
        <button type="button" class="btn btn-video-icon js-video-mute" aria-pressed="false" aria-label="Mute microphone" title="Mute microphone" <?= ! $canOperate ? 'disabled' : '' ?>><i class="bi bi-mic" aria-hidden="true"></i><span class="sr-only">Mute microphone</span></button>
        <button type="button" class="btn btn-video-icon js-video-camera" aria-pressed="false" aria-label="Disable camera" title="Disable camera" <?= ! $canOperate ? 'disabled' : '' ?>><i class="bi bi-camera-video" aria-hidden="true"></i><span class="sr-only">Disable camera</span></button>
        <button type="button" class="btn btn-outline-danger js-video-end" data-end-url="<?= base_url('doctor/video/' . $appointmentId . '/end') ?>" aria-label="End consultation" title="End consultation" <?= ! $canOperate ? 'disabled' : '' ?>><i class="bi bi-stop-circle mr-1" aria-hidden="true"></i><span>End</span></button>
    </div>

    <footer class="doctor-video-panel-note"><i class="bi bi-shield-check mr-1" aria-hidden="true"></i><span>Only the assigned doctor and this clinic-scoped appointment can access the room. Recording requires explicit clinic configuration and consent.</span></footer>
</div>
