<div class="modal fade" id="clinicModal" tabindex="-1" role="dialog" aria-labelledby="clinicModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <?= form_open('admin/clinics/create', 'id="clinicForm" autocomplete="off" novalidate') ?>
            <input type="hidden" name="clinic_id" value="">
            <div class="modal-header">
                <div><h2 class="modal-title h5 mb-1" id="clinicModalTitle">Add New Clinic</h2><p class="small text-white-50 mb-0">Enter the clinic identity, contact, location, and status.</p></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close clinic form"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="clinicFormError" class="alert alert-danger d-none" role="alert" aria-live="assertive"></div>
                <div class="clinic-form-section"><h3 class="clinic-form-section-title">Clinic details</h3><div class="form-row">
                    <div class="form-group col-md-8"><label for="clinic-name">Clinic name <span class="text-danger">*</span></label><input id="clinic-name" type="text" class="form-control" name="clinic_name" maxlength="150" required><small class="form-text text-muted">Use the official clinic name.</small></div>
                    <div class="form-group col-md-4"><label for="clinic-code">Clinic code <span class="text-danger">*</span></label><input id="clinic-code" type="text" class="form-control text-uppercase" name="clinic_code" maxlength="20" required></div>
                    <div class="form-group col-md-6"><label for="clinic-contact-person">Contact person</label><input id="clinic-contact-person" type="text" class="form-control" name="contact_person" maxlength="100"></div>
                    <div class="form-group col-md-6"><label for="clinic-email">Email</label><input id="clinic-email" type="email" class="form-control" name="email" maxlength="150"></div>
                    <?= view('templates/_telephone_contact', ['idPrefix' => 'clinic', 'mobileName' => 'mobile', 'mobileId' => 'clinic-mobile', 'telephoneCodes' => $telephoneCodes ?? []]) ?>
                </div></div>
                <div class="clinic-form-section"><h3 class="clinic-form-section-title">Location</h3><div class="form-row">
                    <div class="form-group col-md-4"><label for="country_id">Country <span class="text-danger">*</span></label><select class="form-control btSelect" data-live-search="true" title="Select country" name="country_id" id="country_id" required onchange="loadStates($(this).val(), '#clinicForm #state_id')"><option value="">Select country</option><?php foreach ($countries as $country): ?><option value="<?= (int) $country['id'] ?>"><?= esc($country['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group col-md-4"><label for="state_id">State <span class="text-danger">*</span></label><select class="form-control btSelect" data-live-search="true" title="Select state" name="state_id" id="state_id" required onchange="loadCities($(this).val(), '#clinicForm #city_id')"><option value="">Select state</option></select></div>
                    <div class="form-group col-md-4"><label for="city_id">City <span class="text-danger">*</span></label><select class="form-control btSelect" data-live-search="true" title="Select city" name="city_id" id="city_id" required><option value="">Select city</option></select></div>
                    <div class="form-group col-md-8"><label for="clinic-address">Address</label><textarea id="clinic-address" class="form-control" rows="3" name="address" maxlength="2000"></textarea></div>
                    <div class="form-group col-md-4"><label for="clinic-pincode">Pincode</label><input id="clinic-pincode" type="text" class="form-control" name="pincode" maxlength="10"></div>
                </div></div>
                <div class="clinic-form-section mb-0"><h3 class="clinic-form-section-title">Operations</h3><div class="form-row"><div class="form-group col-md-6 mb-md-0"><label for="clinic_status">Status <span class="text-danger">*</span></label><select class="form-control btSelect" name="status" id="clinic_status" required><option value="1">Active</option><option value="0">Inactive</option></select></div></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn theme-btn save"><i class="bi bi-check-circle mr-1" aria-hidden="true"></i> Save Clinic</button></div>
            <?= form_close() ?>
        </div>
    </div>
</div>
