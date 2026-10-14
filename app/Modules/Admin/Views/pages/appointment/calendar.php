<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$calendarToday = $calendarToday ?? (new \DateTimeImmutable('now', new \DateTimeZone((string) config('App')->appTimezone)))->format('Y-m-d');
$calendarTimezone = $calendarTimezone ?? (string) config('App')->appTimezone;
$clinics = $clinics ?? [];
$doctors = $doctors ?? [];
$statusOptions = [
    'scheduled' => 'Scheduled',
    'confirmed' => 'Confirmed',
    'checked_in' => 'Checked In',
    'in_queue' => 'In Queue',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show' => 'No Show',
];
?>
<main class="dashboard-container admin-appointment-calendar-page"
      data-calendar-today="<?= esc($calendarToday) ?>"
      data-calendar-timezone="<?= esc($calendarTimezone) ?>">
    <?= view('templates/_breadcrumb', [
        'module' => 'admin',
        'menu_title' => 'Appointment Management',
        'page' => 'Calendar View',
    ]) ?>

    <section class="ri-calendar-header" aria-labelledby="appointmentCalendarPageTitle">
        <div class="ri-calendar-header-copy">
            <p class="text-muted text-uppercase small mb-1">Appointment Management</p>
            <h1 id="appointmentCalendarPageTitle" class="h4 mb-1">Appointment calendar</h1>
            <p class="text-muted mb-0">Review patient, doctor, service, queue, and appointment status by day, week, or month.</p>
        </div>
        <div class="ri-calendar-header-actions">
            <a href="<?= base_url('admin/appointments') ?>" class="btn btn-sm theme-btn"><i class="bi bi-list-ul mr-1" aria-hidden="true"></i>List View</a>
        </div>
    </section>

    <section class="ri-calendar-toolbar" aria-label="Calendar controls">
        <div class="ri-calendar-toolbar-row">
            <div class="ri-calendar-view-switch" role="group" aria-label="Calendar view">
                <span class="ri-calendar-toolbar-label">View</span>
                <button type="button" class="btn btn-sm btn-outline-secondary appointment-calendar-view" data-view="day">Day</button>
                <button type="button" class="btn btn-sm btn-outline-secondary appointment-calendar-view" data-view="week">Week</button>
                <button type="button" class="btn btn-sm btn-outline-secondary appointment-calendar-view is-active" data-view="month">Month</button>
            </div>
            <div class="ri-calendar-navigation" role="group" aria-label="Calendar navigation">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="appointmentCalendarPrevious" aria-label="Previous period"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="appointmentCalendarToday">Today</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="appointmentCalendarNext" aria-label="Next period"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                <label class="ri-calendar-date-jump mb-0">
                    <span class="sr-only">Jump to date</span>
                    <input type="text" class="form-control form-control-sm" id="appointmentCalendarDate" placeholder="dd-mm-yyyy" inputmode="numeric" autocomplete="off" aria-label="Jump to date">
                </label>
            </div>
        </div>

        <form id="appointmentCalendarFilters" class="ri-calendar-filters" data-no-global-loader>
            <div class="ri-calendar-filter-search">
                <label for="appointmentCalendarSearch">Search</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span></div>
                    <input type="search" class="form-control" id="appointmentCalendarSearch" name="search" placeholder="Appointment no., patient, doctor, service">
                </div>
            </div>
            <div>
                <label for="appointmentCalendarStatus">Status</label>
                <select class="form-control form-control-sm" id="appointmentCalendarStatus" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statusOptions as $value => $label): ?><option value="<?= esc($value) ?>"><?= esc($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="appointmentCalendarType">Type</label>
                <select class="form-control form-control-sm" id="appointmentCalendarType" name="appointment_type">
                    <option value="">All types</option>
                    <option value="in_person">In Person</option>
                    <option value="video">Video</option>
                    <option value="phone">Phone</option>
                </select>
            </div>
            <div>
                <label for="appointmentCalendarClinic">Clinic</label>
                <select class="form-control form-control-sm" id="appointmentCalendarClinic" name="clinic_id">
                    <option value="">All clinics</option>
                    <?php foreach ($clinics as $clinic): ?><option value="<?= (int) ($clinic['id'] ?? 0) ?>"><?= esc($clinic['clinic_name'] ?? '') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="appointmentCalendarDoctor">Doctor</label>
                <select class="form-control form-control-sm" id="appointmentCalendarDoctor" name="doctor_id">
                    <option value="">All doctors</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <?php $doctorName = trim(implode(' ', array_filter([$doctor['first_name'] ?? '', $doctor['last_name'] ?? ''], static fn ($part) => $part !== null && $part !== ''))); ?>
                        <option value="<?= (int) ($doctor['id'] ?? 0) ?>"><?= esc($doctorName . (($doctor['doctor_code'] ?? '') !== '' ? ' (' . $doctor['doctor_code'] . ')' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="appointmentCalendarDateFrom">Date from</label>
                <input type="text" class="form-control form-control-sm" id="appointmentCalendarDateFrom" name="date_from" placeholder="dd-mm-yyyy" inputmode="numeric" autocomplete="off">
            </div>
            <div>
                <label for="appointmentCalendarDateTo">Date to</label>
                <input type="text" class="form-control form-control-sm" id="appointmentCalendarDateTo" name="date_to" placeholder="dd-mm-yyyy" inputmode="numeric" autocomplete="off">
            </div>
            <div class="ri-calendar-filter-actions">
                <button type="submit" class="btn btn-sm theme-btn"><i class="bi bi-filter mr-1" aria-hidden="true"></i>Apply</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="appointmentCalendarReset">Reset</button>
            </div>
        </form>
    </section>

    <div class="ri-calendar-summary-row">
        <h2 id="appointmentCalendarTitle" class="h5 mb-0"></h2>
        <div id="appointmentCalendarResultSummary" class="small text-muted" aria-live="polite"></div>
    </div>

    <div class="ri-calendar-legend" aria-label="Appointment status legend">
        <?php foreach (['scheduled' => 'Scheduled', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In', 'in_queue' => 'In Queue', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show'] as $value => $label): ?>
            <?php $tone = ['scheduled' => 'blue', 'confirmed' => 'blue', 'checked_in' => 'amber', 'in_queue' => 'orange', 'completed' => 'green', 'cancelled' => 'red', 'no_show' => 'gray'][$value] ?? 'charcoal'; ?>
            <span class="badge ri-status ri-status--<?= esc($tone) ?>"><span class="ri-status-dot" aria-hidden="true"></span><?= esc($label) ?></span>
        <?php endforeach; ?>
    </div>

    <div id="appointmentCalendarLiveStatus" class="sr-only" aria-live="polite"></div>
    <div id="appointmentCalendarLoading" class="ri-calendar-state" hidden aria-live="polite"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading appointments…</div>
    <div id="appointmentCalendarEmpty" class="ri-calendar-state" hidden>
        <i class="bi bi-calendar-x" aria-hidden="true"></i>
        <strong>No appointments found</strong>
        <span>Try a different date range or clear the filters.</span>
    </div>
    <div id="appointmentCalendar" class="ri-calendar-surface" role="region" aria-label="Appointment calendar" aria-live="polite" aria-busy="false"></div>
</main>

<div class="modal fade" id="appointmentCalendarDetailsModal" tabindex="-1" role="dialog" aria-labelledby="appointmentCalendarDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable ri-scroll-modal" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="text-muted text-uppercase small mb-1">Appointment details</p>
                    <h2 class="modal-title h5 mb-0" id="appointmentCalendarDetailsTitle">Appointment</h2>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="appointmentCalendarDetailsBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Close</button>
                <a href="#" class="btn btn-sm theme-btn" id="appointmentCalendarOpenDetails">Open appointment</a>
            </div>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/appointment/appointment_calendar.js?v=20261010-calendar-filters1') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
