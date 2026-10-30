<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-video-page" aria-labelledby="doctor-video-title">
    <div class="page-heading">
        <div>
            <a href="<?= base_url('doctor/appointments') ?>" class="btn btn-sm btn-light mb-3"><i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>Back to appointments</a>
            <div class="page-eyebrow">Tele-consultation</div>
            <h1 id="doctor-video-title" class="h3 mb-1">Video consultation</h1>
        </div>
        <span class="badge badge-light">Secure provider boundary</span>
    </div>
    <section class="card doctor-video-card">
        <div class="card-body"><?= view(DOCTOR_VIEW . 'pages/video_panel', get_defined_vars()) ?></div>
    </section>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://sdk.twilio.com/js/video/releases/2.29.0/twilio-video.min.js"></script>
<script src="<?= base_url('assets/js/video/twilio-room.js?v=20261030.5') ?>"></script>
<script src="<?= base_url('assets/js/video/video-ui.js?v=20261030.1') ?>"></script>
<script src="<?= base_url('assets/js/video/doctor-video.js?v=20261030.1') ?>"></script>
<?= $this->endSection() ?>
