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

<!-- data table -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap4.min.css">

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



  <div class="preloader parent_div" style="/* display: none; */">
    <div class="child_div">
       <div class="preloader_icon" style="display: block;">
          <div style="width: 150px;font-size: 20px;color: #ffffff;position: relative;top: 60px;left: -20px;">Please Wait...</div>
       </div>
    </div>
  </div>

  <!-- Js alert msg -->
  <div id="js_alert"></div>


  <!-- ================= PRESERVED SIDEBAR MENU ================= -->
  <?= $this->renderSection('side-menu')?>
  

  <!-- ================= MAIN CONTENT WRAPPER ================= -->
  <div id="content-wrapper">

    <!-- STICKY TOP CONTAINER (Header + Top Menu Nav) -->
    <?= $this->renderSection('top-menu')?>

    <!-- DASHBOARD CONTENT GRID -->
    <?= $this->renderSection('main-content')?>

  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <!-- Bootstrap 5 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap4.min.js"></script>


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
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
      document.getElementById('sidebar').classList.toggle('show');
    });

    /*bt Select init*/
    $(function () {
      $('.btSelect').selectpicker();
    });


    /*datetime init*/
    $('.datepicker').datepicker({
        format: 'dd-mm-yyyy',
        autoclose: true,
        todayHighlight: true
    });



  </script>
</body>
</html>