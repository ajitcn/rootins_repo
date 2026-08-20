<nav id="sidebar">
    <div class="sidebar-brand">
      <span class="brand-img">
        <img src="<?= base_url('assets/img/logo.png')?>">
      </span>
      <span class="system-tag">Admin</span>
    </div>

    <ul class="sidebar-nav" id="sidebarMenu">
      <li class="nav-item">
        <a href="#" class="nav-link active">
          <i class="bi bi-speedometer2 menu-icon"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link collapsed" data-toggle="collapse" href="#clinicMenu" role="button" aria-expanded="false">
          <i class="fa-solid fa-house-medical menu-icon"></i> 
          <span>Clinic Management</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="clinicMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="<?= base_url('admin/clinics')?>" class="submenu-link">Clinics</a>
          </div>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" data-toggle="collapse" href="#doctorsMenu" role="button" aria-expanded="false">
          <i class="fa-solid fa-user-doctor menu-icon"></i> 
          <span>Doctor Management</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="doctorsMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="<?= base_url('admin/doctors')?>" class="submenu-link">Doctors</a>
            <a href="<?= base_url('admin/doctor-schedules')?>" class="submenu-link">Doctor Schedules</a>
            <a href="<?= base_url('admin/doctor-leaves')?>" class="submenu-link">Doctor Leaves</a> 
            <a href="<?= base_url('admin/doctor-fees')?>" class="submenu-link">Consultation Fees</a>
            <a href="<?= base_url('admin/doctor-commissions')?>" class="submenu-link">Commission Plans</a>
            <a href="#" class="submenu-link">Doctor Documents</a> 
          </div>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" data-toggle="collapse" href="#patientsMenu" role="button" aria-expanded="false">
          <i class="fa-solid fa-user-injured menu-icon"></i>
          <span>Patient Management</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="patientsMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="#" class="submenu-link">Patients</a>
            <a href="#" class="submenu-link">Patient Registration</a>
            <a href="#" class="submenu-link">Medical History</a>
            <a href="#" class="submenu-link">OPD Consultation</a>
            <a href="#" class="submenu-link">Prescriptions</a>
            <a href="#" class="submenu-link">Diagnostic Tests</a>
            <a href="#" class="submenu-link">Treatment Plans</a> 
            <a href="#" class="submenu-link">Diet Plans</a> 
            <a href="#" class="submenu-link">Patient Documents</a> 
          </div>
        </div>
      </li>
      <li class="nav-item"  title="Appointment Management">
        <a class="nav-link collapsed" data-toggle="collapse" href="#appointmentsMenu" role="button" aria-expanded="false">
          <i class="fa-regular fa-calendar-check menu-icon"></i>
          <span>Appointment Manage...</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="appointmentsMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="#" class="submenu-link">Appointments</a>
            <a href="#" class="submenu-link">List View</a>
            <a href="#" class="submenu-link">Calendar View</a>
            <a href="#" class="submenu-link">Waiting Queue</a>
            <a href="#" class="submenu-link">Appointment Reports</a> 
          </div>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" data-toggle="collapse" href="#billingsMenu" role="button" aria-expanded="false">
          <i class="fa-solid fa-money-bill menu-icon"></i>
          <span>Billing</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="billingsMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="#" class="submenu-link">Invoices</a>
            <a href="#" class="submenu-link">Payments</a>
            <a href="#" class="submenu-link">Expenses</a>
            <a href="#" class="submenu-link">Packages</a>
            <a href="#" class="submenu-link">Refunds</a> 
            <a href="#" class="submenu-link">Billing Reports</a> 
          </div>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" data-toggle="collapse" href="#administrationMenu" role="button" aria-expanded="false">
          <i class="fa-solid fa-user-tie menu-icon"></i>
          <span>Administration</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="administrationMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="#" class="submenu-link">Users</a>
            <a href="#" class="submenu-link">Roles</a>
            <a href="#" class="submenu-link">Permissions</a>
            <a href="#" class="submenu-link">Audit Logs</a>
          </div>
        </div>
      </li>

      <li class="nav-item"  title="Masters & Configuration">
        <a class="nav-link collapsed" data-toggle="collapse" href="#configMenu" role="button" aria-expanded="false">
          <i class="fa-solid fa-gear menu-icon"></i>
          <span>Masters & Config...</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="configMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="#" class="submenu-link">Doctor Specializations</a>
            <a href="#" class="submenu-link">Consultation Fee Templates</a>
            <a href="#" class="submenu-link">Commission Templates</a>
            <a href="#" class="submenu-link">Departments</a>
            <a href="#" class="submenu-link">Services</a>
            <a href="#" class="submenu-link">Payment Modes</a>
            <a href="#" class="submenu-link">Tax Rates</a> 
          </div>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" data-toggle="collapse" href="#settingMenu" role="button" aria-expanded="false">
          <i class="fa-solid fa-wrench menu-icon"></i>
          <span>Settings</span>
          <i class="bi bi-chevron-down arrow"></i>
        </a>
        <div class="collapse" id="settingMenu" data-parent="#sidebarMenu">
          <div class="sidebar-submenu">
            <a href="#" class="submenu-link">General Settings</a>
            <a href="#" class="submenu-link">Clinic Settings</a>
            <a href="#" class="submenu-link">Email (SMTP)</a>
            <a href="#" class="submenu-link">SMS Gateway</a>
            <a href="#" class="submenu-link">WhatsApp API</a>
            <a href="#" class="submenu-link">Notification Settings</a>
            <a href="#" class="submenu-link">Invoice Templates</a> 
            <a href="#" class="submenu-link">Prescription Templates</a> 
            <a href="#" class="submenu-link">Backup & Restore</a> 
            <a href="#" class="submenu-link">System Logs</a> 
            <a href="#" class="submenu-link">Application Preferences</a> 
          </div>
        </div>
      </li>
 

      <li class="nav-item">
        <a href="#" class="nav-link">
          <i class="bi bi-question-circle-fill menu-icon"></i>
          <span>Help</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="#" class="nav-link">
          <i class="bi bi-house-door-fill menu-icon"></i>
          <span>Home</span>
        </a>
      </li>
    </ul>
  </nav>