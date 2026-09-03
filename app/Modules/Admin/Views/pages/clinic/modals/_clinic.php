<!-- Add new Clinic Modal -->
<div class="modal fade" id="clinicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <?= form_open('admin/clinics/create', 'id="clinicForm" autocomplete="off" ')?>
                <input type="hidden" name="clinic_id">
                <div class="modal-header">
                    <h5 class="modal-title"> Add New Clinic </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Clinic Name -->
                        <div class="col-md-6">
                            <label class="form-label"> Clinic Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="clinic_name" maxlength="150">
                        </div>
                        <!-- Clinic Code -->
                        <div class="col-md-6">
                            <label class="form-label"> Clinic Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="clinic_code" maxlength="5">
                        </div>
                        <!-- Contact Person -->
                        <div class="col-md-4">
                            <label class="form-label"> Contact Person </label>
                            <input type="text" class="form-control" name="contact_person">
                        </div>
                        <!-- Email -->
                        <div class="col-md-4">
                            <label class="form-label"> Email </label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <!-- Mobile -->
                        <div class="col-md-4">
                            <label class="form-label"> Mobile </label>
                            <input type="text" class="form-control" name="mobile">
                        </div>
                        <!-- Country -->
                        <div class="col-md-4">
                            <label class="form-label"> Country </label>
                            <select class="form-control btSelect" data-live-search="true" title="Select City" name="country_id" id="country_id" onchange="loadStates($(this).val(), '#clinicForm #state_id')">
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= $country['id'] ?>">
                                        <?= esc($country['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- State -->
                        <div class="col-md-4">
                            <label class="form-label"> State </label>
                            <select class="form-control btSelect" data-live-search="true" title="Select State" name="state_id" id="state_id" onchange="loadCities($(this).val(), '#clinicForm #city_id')"></select>
                        </div>
                        <!-- City -->
                        <div class="col-md-4">
                            <label class="form-label"> City </label>
                            <select class="form-control btSelect" data-live-search="true" title="Select City" name="city_id" id="city_id"></select>
                        </div>
                        <!-- Address -->
                        <div class="col-md-12">
                            <label class="form-label"> Address </label>
                            <textarea class="form-control" rows="3" name="address"></textarea>
                        </div>
                        <!-- Pincode -->
                        <div class="col-md-6">
                            <label class="form-label"> Pincode </label>
                            <input type="text" class="form-control" name="pincode">
                        </div>
                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label" for="clinic_status"> Status </label>
                            <select class="form-control" name="status" id="clinic_status">
                                <option value="1"> Active </option>
                                <option value="0"> Inactive </option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary save">
                        <i class="bi bi-check-circle me-1"></i> Save Clinic </button>
                </div>
                <?= form_close()?>
        </div>
    </div>
</div>
