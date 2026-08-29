<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Appointment Management',
        'page'       => 'Calendar View',
    ]) ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="btn-group mb-2 mb-md-0" role="group" aria-label="Calendar view">
            <button type="button" class="btn btn-outline-primary appointment-calendar-view" data-view="day">Day</button>
            <button type="button" class="btn btn-outline-primary appointment-calendar-view" data-view="week">Week</button>
            <button type="button" class="btn btn-primary appointment-calendar-view" data-view="month">Month</button>
        </div>
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="appointmentCalendarPrevious">&lsaquo;</button>
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="appointmentCalendarToday">Today</button>
            <button type="button" class="btn btn-sm btn-outline-secondary mr-3" id="appointmentCalendarNext">&rsaquo;</button>
            <a href="<?= base_url('admin/appointments') ?>" class="btn btn-sm theme-btn">List View</a>
        </div>
    </div>

    <h5 id="appointmentCalendarTitle" class="mb-3"></h5>
    <div id="appointmentCalendar" class="table-responsive border rounded bg-white"></div>
</main>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/appointment/appointment_calendar.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
