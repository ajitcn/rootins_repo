<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="base-url" content="<?= base_url() ?>">
  <title>ROOTINS::Dashboard</title>
  <!-- Bootstrap 5 CSS -->
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
  <link href="<?= base_url('assets/css/style.css')?>" rel="stylesheet" />
 
</head>
<body>
  <a class="sr-only sr-only-focusable" href="#main-content">Skip to main content</a>



  <div class="preloader parent_div" style="/* display: none; */">
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
  

  <!-- ================= MAIN CONTENT WRAPPER ================= -->
  <div id="content-wrapper">

    <!-- STICKY TOP CONTAINER (Header + Top Menu Nav) -->
    <?= $this->renderSection('top-menu')?>

    <!-- DASHBOARD CONTENT GRID -->
    <div id="main-content" tabindex="-1">
      <?= $this->renderSection('main-content')?>
    </div>

  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <!-- Bootstrap 5 Bundle JS -->
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
  <script src="<?= base_url('assets/js/common.js')?>"></script>
  

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
      section.textContent = sidebar.querySelector('.system-tag')?.textContent?.trim() === 'Farmassist' ? 'Fulfilment' : 'Workspace';
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
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
      sidebar?.classList.toggle('show');
      this.setAttribute('aria-expanded', sidebar?.classList.contains('show') ? 'true' : 'false');
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

    /*bt Select init*/
    $(function () {
      $('select[data-live-search], .btSelect').each(function () {
        if (!$(this).hasClass('selectpicker')) {
          $(this).selectpicker({noneSelectedText: 'Select an option', liveSearchPlaceholder: 'Type to search...'});
        }
      });
    });


    /* Shared datepicker init. Keep the submitted value in the server's ISO format. */
    function isoDate(value) {
      const text = String(value || '').trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;
      const match = text.match(/^(\d{2})-(\d{2})-(\d{4})$/);
      return match ? `${match[3]}-${match[2]}-${match[1]}` : '';
    }

    function displayDate(value) {
      const iso = isoDate(value);
      return iso ? `${iso.slice(8, 10)}-${iso.slice(5, 7)}-${iso.slice(0, 4)}` : '';
    }

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
            const hidden = $('<input>', {type: 'hidden', name: name, 'data-datepicker-hidden-for': id});
            field.removeAttr('name').after(hidden).data('datepicker-hidden', hidden);
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
