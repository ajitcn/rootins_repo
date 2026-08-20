<?= $this->extend(ADMIN_VIEW.'layout')?>


 <?= $this->section('main-content')?>

 <main class="dashboard-container">

       
      <div class="row g-3 mb-3"> 
        
        <div class="col-12 col-lg-12">
          <div class="row g-3">
            <div class="col-12 col-sm-4">
              <div class="tblr-card p-3 mb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="stat-title">Total Patients</div>
                    <div class="stat-value">7,893</div>
                  </div>
                  <span class="stat-badge success"><i class="bi bi-graph-up-arrow"></i> +2,055</span>
                </div>
                <div class="small text-muted mt-2" style="font-size:0.75rem;">Recorded in last 30 days</div>
              </div>
            </div>

            <div class="col-12 col-sm-4">
              <div class="tblr-card p-3 mb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="stat-title">Total Follow-Up</div>
                    <div class="stat-value">6,544</div>
                  </div>
                  <span class="stat-badge primary"><i class="bi bi-clock"></i> 99 today</span>
                </div>
                <div class="small text-muted mt-2" style="font-size:0.75rem;">Active pipeline touchpoints</div>
              </div>
            </div>

            <div class="col-12 col-sm-4">
              <div class="tblr-card p-3 mb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="stat-title">Total Orders</div>
                    <div class="stat-value">1,530</div>
                  </div>
                  <span class="stat-badge success"><i class="bi bi-check2-circle"></i> 365 this mo.</span>
                </div>
                <div class="small text-muted mt-2" style="font-size:0.75rem;">Order created</div>
              </div>
            </div> 
          </div>
        </div>
        <!-- User Performance Hero Card -->
        <div class="col-12 col-lg-6">
          <div class="user-hero-card h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-white-subtle text-white fw-medium" style="font-size:0.7rem; background:rgba(255,255,255,0.15);">IMPORTANT</span>
                <span class="small opacity-75"><?= date('d M Y')?></span>
              </div>
              <h5 class="fw-bold mb-1">Welcome! Ajit</h5>
              <div class="small opacity-75 mb-3">Action required: <strong class="text-info">15</strong></div>
            </div>

           
            <div class="row g-2">
              <div class="col-4">
                <div class="hero-stat-pill">
                  <div class="small opacity-75" style="font-size:0.65rem;"><i class="fa-regular fa-calendar-check"></i> APPOINTMENT</div>
                  <div class="fw-bold fs-6">7</div>
                </div>
              </div>
              <div class="col-4">
                <div class="hero-stat-pill">
                  <div class="small opacity-75" style="font-size:0.65rem;"><i class="fa-solid fa-prescription"></i> APPROVAL</div>
                  <div class="fw-bold fs-6">4</div>
                </div>
              </div>
              <div class="col-4">
                <div class="hero-stat-pill">
                  <div class="small opacity-75" style="font-size:0.65rem;">PENDING ORDERS</div>
                  <div class="fw-bold fs-6">4</div>
                </div>
              </div>
            </div>
          </div>
        </div> 


        <div class="col-12 col-lg-6">
          <div class="user-hero-card h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-white-subtle text-white fw-medium" style="font-size:0.7rem; background:rgba(255,255,255,0.15);">IMPORTANT</span>
                <span class="small opacity-75"><?= date('d M Y')?></span>
              </div>
              <h5 class="fw-bold mb-1">Welcome! Ajit</h5>
              <div class="small opacity-75 mb-3">Action required: <strong class="text-info">15</strong></div>
            </div>

           
            <div class="row g-2">
              <div class="col-4">
                <div class="hero-stat-pill">
                  <div class="small opacity-75" style="font-size:0.65rem;"><i class="fa-regular fa-calendar-check"></i> APPOINTMENT</div>
                  <div class="fw-bold fs-6">7</div>
                </div>
              </div>
              <div class="col-4">
                <div class="hero-stat-pill">
                  <div class="small opacity-75" style="font-size:0.65rem;"><i class="fa-solid fa-prescription"></i> APPROVAL</div>
                  <div class="fw-bold fs-6">4</div>
                </div>
              </div>
              <div class="col-4">
                <div class="hero-stat-pill">
                  <div class="small opacity-75" style="font-size:0.65rem;">PENDING ORDERS</div>
                  <div class="fw-bold fs-6">4</div>
                </div>
              </div>
            </div>
          </div>
        </div> 
      </div>

      <!-- Action Required Chips Panel -->
    <!--   <div class="tblr-card p-3 mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-exclamation-square-fill text-danger"></i>
          <h6 class="tblr-card-title">Leads Needing Action & Review</h6>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="#" class="review-chip">Lead Created <span class="chip-count">16</span></a>
          <a href="#" class="review-chip">Call Disconnect <span class="chip-count">170</span></a>
          <a href="#" class="review-chip">Ringing <span class="chip-count">876</span></a>
          <a href="#" class="review-chip">Call Back <span class="chip-count">988</span></a>
          <a href="#" class="review-chip">Pic Waiting <span class="chip-count">36</span></a>
          <a href="#" class="review-chip">On E-Mail <span class="chip-count">2</span></a>
          <a href="#" class="review-chip">Taken on Whatsapp <span class="chip-count">698</span></a>
          <a href="#" class="review-chip">Not Reachable <span class="chip-count">68</span></a>
        </div>
      </div> -->

      <!-- Tabler Data Tables Section -->
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <div class="tblr-card mb-0">
            <div class="tblr-card-header">
              <h6 class="tblr-card-title">Upcoming Follow-Ups</h6>
              <a href="#" class="btn btn-sm btn-light py-0 px-2 fs-7">View All</a>
            </div>
            <div class="table-responsive">
              <table class="table table-tabler">
                <thead>
                  <tr>
                    <th>Lead Name</th>
                    <th>Status</th>
                    <th>Schedule Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><span class="fw-semibold">Nadeem Shaikh</span> <span class="text-muted small">#22223</span></td>
                    <td><span class="badge bg-success-subtle text-success">Open</span></td>
                    <td class="text-muted">06-05-2026 12:00PM</td>
                  </tr>
                  <tr>
                    <td><span class="fw-semibold">Sahil Chikhale</span> <span class="text-muted small">#25687</span></td>
                    <td><span class="badge bg-success-subtle text-success">Open</span></td>
                    <td class="text-muted">21-06-2026 12:00PM</td>
                  </tr>
                  <tr>
                    <td><span class="fw-semibold">RAJESH MARATHE</span> <span class="text-muted small">#27141</span></td>
                    <td><span class="badge bg-success-subtle text-success">Open</span></td>
                    <td class="text-muted">13-07-2026 12:00PM</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="tblr-card mb-0">
            <div class="tblr-card-header">
              <h6 class="tblr-card-title">Latest Appointments</h6>
              <a href="#" class="btn btn-sm btn-light py-0 px-2 fs-7">View All</a>
            </div>
            <div class="table-responsive">
              <table class="table table-tabler">
                <thead>
                  <tr>
                    <th>Patient</th>
                    <th>Status</th>
                    <th>Appointment Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="fw-semibold">PRASHANT S. PEDNEKAR</td>
                    <td><span class="badge bg-primary-subtle text-primary">Confirmed</span></td>
                    <td class="text-muted">09-08-2026 10:46AM</td>
                  </tr>
                  <tr>
                    <td class="fw-semibold">Rudra Banerjee</td>
                    <td><span class="badge bg-primary-subtle text-primary">Confirmed</span></td>
                    <td class="text-muted">08-08-2026 04:31PM</td>
                  </tr>
                  <tr>
                    <td class="fw-semibold">Sandeep Jaiswal</td>
                    <td><span class="badge bg-primary-subtle text-primary">Confirmed</span></td>
                    <td class="text-muted">07-08-2026 12:30PM</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </main>


 <?= $this->endSection()?>