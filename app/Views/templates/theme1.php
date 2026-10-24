<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="base-url" content="<?= base_url() ?>">
  <title>ROOTINS::Dashboard</title>
  <!-- Bootstrap 4.6.2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <!-- font-awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.0/css/all.min.css"/>


  <!-- bootstrap datetime -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css"> 



  <!-- Inter Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin> 
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- bt-select2 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/css/bootstrap-select.min.css">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

 

  <!-- custom css -->
  <link href="<?= base_url('assets/css/theme1.css')?>" rel="stylesheet" />
  <link href="<?= base_url('assets/css/style.css?v=20261021-schedule-scroll1')?>" rel="stylesheet" />
  <link href="<?= base_url('assets/css/ui-components.css?v=20261008-audit')?>" rel="stylesheet" />
  <link href="<?= base_url('assets/css/rootins-pagination.css?v=20261010-pagination1')?>" rel="stylesheet" />
  <link href="<?= base_url('assets/css/print.css?v=20261016-rootins-print1')?>" rel="stylesheet" />
  <?= $this->renderSection('styles') ?>
  <link href="<?= base_url('assets/css/rootins-monochrome.css?v=20261010-calendar-filters1')?>" rel="stylesheet" />
  <link href="<?= base_url('assets/css/responsive-tables.css?v=20261019-native-tables6')?>" rel="stylesheet" />
 
</head>
<?php $currentUiPath = trim(uri_string(), '/'); ?>
<body class="<?= trim(implode(' ', array_filter([
    $currentUiPath === 'patient/login' ? 'patient-login-shell' : '',
    str_starts_with($currentUiPath, 'admin') ? 'admin-shell' : '',
]))) ?>">
  <a class="sr-only sr-only-focusable" href="#main-content">Skip to main content</a>



  <div class="preloader parent_div" style="display: none;">
    <div class="child_div">
       <div class="preloader_icon" style="display: block;">
          <div style="width: 150px;font-size: 20px;color: #ffffff;position: relative;top: 60px;left: -20px;">Please Wait...</div>
       </div>
    </div>
  </div>

  <!-- Js alert msg -->
  <div id="js_alert" aria-live="polite" aria-atomic="true"></div>


  <!-- ================= PRESERVED SIDEBAR MENU ================= -->
  <?= $this->renderSection('side-menu')?>
  <div
    class="rootins-shell-overlay"
    id="sidebarOverlay"
    data-sidebar-close
    aria-hidden="true"
  ></div>
  

  <!-- ================= MAIN CONTENT WRAPPER ================= -->
  <div id="content-wrapper">

    <!-- STICKY TOP CONTAINER (Header + Top Menu Nav) -->
    <?= $this->renderSection('top-menu')?>

    <!-- DASHBOARD CONTENT GRID -->
    <div id="main-content" tabindex="-1">
      <?= $this->renderSection('main-content')?>
    </div>

    <?= $this->renderSection('footer')?>

  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <!-- Bootstrap 4.6.2 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>


  <!-- bt select2 -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/js/bootstrap-select.min.js"></script>

  <!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- bootstrap datetime -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script> 

<script src="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>



  <!-- custom js -->
  <script src="<?= base_url('assets/js/app.js')?>"></script>
  <script src="<?= base_url('assets/js/routes.js')?>"></script>
  <script src="<?= base_url('assets/js/common.js?v=20261009-preloader1')?>"></script>
  <script src="<?= base_url('assets/js/status-colors.js?v=20261024-status5')?>"></script>
  <script src="<?= base_url('assets/js/actions-menu.js?v=20261009-actions4')?>"></script>
  <script src="<?= base_url('assets/js/modal-layer.js?v=20261009-modal2')?>"></script>
  

  <?= $this->renderSection('scripts')?>

  <script>
    const sidebar = document.getElementById('sidebar');
    const topHeader = document.querySelector('.top-header');
    sidebar?.querySelectorAll('.nav-link').forEach(function (link) {
      const label = link.querySelector('span')?.textContent?.trim();
      if (label && !link.getAttribute('title')) link.setAttribute('title', label);
      if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
    });
    const sidebarNav = sidebar?.querySelector('.sidebar-nav');
    if (sidebarNav && !sidebarNav.querySelector('.sidebar-section-title')) {
      const section = document.createElement('div');
      section.className = 'sidebar-section-title';
      section.setAttribute('aria-hidden', 'true');
      section.textContent = sidebar.querySelector('.system-tag')?.textContent?.trim() === 'Pharmacy' ? 'Fulfilment' : 'Workspace';
      sidebarNav.prepend(section);
    }
    if (topHeader && !document.getElementById('sidebarCollapseToggle')) {
      const collapseButton = document.createElement('button');
      collapseButton.id = 'sidebarCollapseToggle';
      collapseButton.type = 'button';
      collapseButton.className = 'btn btn-sm btn-light d-none d-lg-inline-flex mr-2';
      collapseButton.setAttribute('aria-controls', 'sidebar');
      collapseButton.setAttribute('aria-expanded', 'true');
      collapseButton.setAttribute('aria-label', 'Collapse navigation');
      collapseButton.innerHTML = '<i class="bi bi-chevron-double-left" aria-hidden="true"></i>';
      topHeader.querySelector('.d-flex.align-items-center')?.prepend(collapseButton);
    }
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay')
      || document.getElementById('pharmacyShellOverlay');
    const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');
    function setSidebarOpen(open) {
      sidebar?.classList.toggle('show', open);
      const mobileOpen = open && window.innerWidth < 992;
      document.body.classList.toggle('rootins-nav-open', mobileOpen);
      document.body.classList.toggle(
        'pharmacy-nav-open',
        mobileOpen && !!sidebar?.classList.contains('app-sidebar'),
      );
      sidebar?.setAttribute(
        'aria-hidden',
        mobileOpen || window.innerWidth >= 992 ? 'false' : 'true',
      );
      sidebarToggle?.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
      sidebarToggle?.setAttribute('aria-label', mobileOpen ? 'Close navigation' : 'Open navigation');
    }
    sidebarToggle?.addEventListener('click', function () { setSidebarOpen(!sidebar?.classList.contains('show')); });
    sidebarOverlay?.addEventListener('click', function () { setSidebarOpen(false); });
    sidebarCloseButtons.forEach(function (button) { button.addEventListener('click', function () { setSidebarOpen(false); }); });
    if (window.innerWidth < 992) sidebar?.setAttribute('aria-hidden', 'true');
    sidebar?.querySelectorAll('a.nav-link, a.submenu-link').forEach(function (link) { link.addEventListener('click', function () { if (window.innerWidth < 992) setSidebarOpen(false); }); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && sidebar?.classList.contains('show')) setSidebarOpen(false); });
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 992) {
        sidebar?.classList.remove('show');
        document.body.classList.remove('rootins-nav-open', 'pharmacy-nav-open');
        sidebar?.setAttribute('aria-hidden', 'false');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
        sidebarToggle?.setAttribute('aria-label', 'Open navigation');
      }
    });
    document.getElementById('sidebarCollapseToggle')?.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-collapsed');
      const collapsed = document.body.classList.contains('sidebar-collapsed');
      this.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      this.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
      this.querySelector('i')?.classList.toggle('bi-chevron-double-right', collapsed);
      this.querySelector('i')?.classList.toggle('bi-chevron-double-left', !collapsed);
      try { localStorage.setItem('rootins-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
    });
    try { if (localStorage.getItem('rootins-sidebar-collapsed') === '1') document.body.classList.add('sidebar-collapsed'); } catch (e) {}

    /* Keep secondary record actions together without changing their existing
       links, form submissions, or delegated event targets. */
    function compactAdminRowActions() {
      if (!document.body.classList.contains('admin-shell')) return;
      document.querySelectorAll('.table-responsive table tbody tr td:last-child').forEach(function (cell) {
        if (cell.querySelector('.admin-row-actions-more')) return;
        const controls = Array.from(cell.children).filter(function (element) {
          return element.matches('a.btn, button.btn, form');
        });
        if (controls.length < 3) return;
        const menu = document.createElement('div');
        menu.className = 'dropdown-menu dropdown-menu-right';
        const wrapper = document.createElement('div');
        wrapper.className = 'dropdown admin-row-actions-more';
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-sm btn-outline-secondary dropdown-toggle';
        toggle.setAttribute('data-toggle', 'dropdown');
        toggle.setAttribute('aria-haspopup', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="fa-solid fa-ellipsis-vertical mr-1" aria-hidden="true"></i>More';
        wrapper.appendChild(toggle);
        wrapper.appendChild(menu);
        controls.slice(2).forEach(function (control) {
          if (control.tagName === 'FORM') {
            control.classList.add('admin-action-form');
            menu.appendChild(control);
          } else {
            control.classList.remove('btn-sm');
            control.classList.add('dropdown-item');
            menu.appendChild(control);
          }
        });
        cell.appendChild(wrapper);
      });
    }
    function prepareAdminResponsiveTables() {
      if (!document.body.classList.contains('admin-shell')) return;
      document.querySelectorAll('.table-responsive table').forEach(function (table) {
        const labels = Array.from(table.querySelectorAll('thead th')).map(function (header) {
          return header.textContent.trim();
        });
        table.querySelectorAll('tbody tr').forEach(function (row) {
          if (row.querySelector('td[colspan]')) return;
          row.querySelectorAll('td').forEach(function (cell, index) {
            if (labels[index] && !cell.hasAttribute('data-label')) cell.setAttribute('data-label', labels[index]);
          });
        });
        /* All application tables remain native tables at every breakpoint;
           their .table-responsive wrappers provide the mobile scroll surface. */
        table.classList.remove('admin-responsive-stack');
      });
    }
    function refreshAdminListPresentation() {
      prepareAdminResponsiveTables();
      compactAdminRowActions();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refreshAdminListPresentation);
    else refreshAdminListPresentation();
    window.addEventListener('resize', prepareAdminResponsiveTables);

    /*bt Select init*/
    $(function () {
      $('select[data-live-search], .btSelect').each(function () {
        if (!$(this).hasClass('selectpicker')) {
          $(this).selectpicker({noneSelectedText: 'Select an option', liveSearchPlaceholder: 'Type to search...'});
        }
      });
      if (typeof bindCountryCodeToggles === 'function') bindCountryCodeToggles(document);
    });


    /* Shared datepicker init. Keep the submitted value in the server's ISO format. */
    function isoDate(value) {
      const text = String(value || '').trim();
      const isValid = function (year, month, day) {
        const date = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)));
        return date.getUTCFullYear() === Number(year) && date.getUTCMonth() === Number(month) - 1 && date.getUTCDate() === Number(day);
      };
      if (/^\d{4}-\d{2}-\d{2}$/.test(text) && isValid(text.slice(0, 4), text.slice(5, 7), text.slice(8, 10))) return text;
      const match = text.match(/^(\d{2})-(\d{2})-(\d{4})$/);
      return match && isValid(match[3], match[2], match[1]) ? `${match[3]}-${match[2]}-${match[1]}` : '';
    }

    function displayDate(value) {
      const iso = isoDate(value);
      return iso ? `${iso.slice(8, 10)}-${iso.slice(5, 7)}-${iso.slice(0, 4)}` : '';
    }

    window.rootinsFormatDate = displayDate;

    function formatTime(value) {
      const text = String(value || '').trim();
      const match = text.match(/^(\d{1,2}):(\d{2})(?::\d{2})?(?:\s*([AP]M))?$/i);
      if (!match) return text;
      let hour = Number(match[1]);
      if (match[3]) {
        if (hour < 1 || hour > 12) return text;
        if (match[3].toUpperCase() === 'PM' && hour !== 12) hour += 12;
        if (match[3].toUpperCase() === 'AM' && hour === 12) hour = 0;
      } else if (hour > 23) return text;
      const suffix = hour >= 12 ? 'PM' : 'AM';
      const displayHour = hour % 12 || 12;
      return String(displayHour).padStart(2, '0') + ':' + match[2] + ' ' + suffix;
    }

    window.rootinsFormatTime = formatTime;

    window.rootinsNormalizeTime = function (value) {
      const text = String(value || '').trim();
      const display = formatTime(text);
      const match = display.match(/^(\d{2}):(\d{2}) ([AP]M)$/);
      if (!match) return null;
      let hour = Number(match[1]);
      if (match[3] === 'PM' && hour !== 12) hour += 12;
      if (match[3] === 'AM' && hour === 12) hour = 0;
      return String(hour).padStart(2, '0') + ':' + match[2] + ':00';
    };

    window.rootinsDateToIso = function (selector) {
      return isoDate($(selector).val());
    };

    window.setSharedDateValue = function (selector, value) {
      const field = $(selector);
      field.val(displayDate(value)).trigger('changeDate');
      const hidden = field.data('datepicker-hidden');
      if (hidden) hidden.val(isoDate(value));
    };

    function initSharedDatepickers(root) {
      const fields = $(root || document).find('input[type="date"], .datepicker');
      fields.filter('input[type="date"]').each(function () {
        this.type = 'text';
        $(this).addClass('datepicker').attr({
          placeholder: 'dd-mm-yyyy',
          autocomplete: 'off'
        });
      });
      fields.filter('.datepicker').each(function () {
        const field = $(this);
        field.attr('placeholder', 'dd-mm-yyyy');
        if (!field.data('datepicker-bound')) {
            const name = field.attr('name');
          if (name) {
            const id = field.attr('id') || `datepicker-${Math.random().toString(36).slice(2)}`;
            field.attr('id', id);
            field.attr('data-validation-name', name);
            const submitDisplayDate = field.attr('data-submit-display-date') === 'true';
            const hidden = $('<input>', {type: 'hidden', 'data-datepicker-hidden-for': id});
            if (!submitDisplayDate) {
              hidden.attr('name', name);
              field.removeAttr('name');
            }
            field.after(hidden).data('datepicker-hidden', hidden);
            field.val(displayDate(field.val()));
            field.on('change.sharedDate changeDate.sharedDate', function () {
              hidden.val(isoDate(field.val()));
            });
            hidden.val(isoDate(field.val()));
          }
          field.data('datepicker-bound', true);
        }
        if (!field.data('datepicker')) {
          const options = {
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true,
            clearBtn: true,
            orientation: 'auto',
            keyboardNavigation: true
          };
          if (field.attr('data-datepicker-container')) options.container = field.attr('data-datepicker-container');
          if (field.attr('min')) options.startDate = displayDate(field.attr('min'));
          if (field.attr('max')) options.endDate = displayDate(field.attr('max'));
          field.datepicker(options);
        }
      });
    }

    $(function () {
      initSharedDatepickers(document);
      $(document).on('shown.bs.modal', '.modal', function () {
        initSharedDatepickers(this);
      });
    });



  </script>
</body>
</html>
