<?= $this->extend('templates/theme1') ?>

<?= $this->section('main-content') ?>
<main class="patient-login-page" aria-labelledby="patient-login-title">
    <div class="patient-login-card">
        <section class="patient-login-brand" aria-label="Patient portal information">
            <div class="patient-login-logo"><i class="bi bi-heart-pulse" aria-hidden="true"></i></div>
            <div class="page-eyebrow text-white-50">ROOTINS patient portal</div>
            <h1 class="h2 text-white mb-3">Your care, in one place.</h1>
            <p class="text-white-50 mb-0">Manage appointments, prescriptions, medicine orders, invoices, and clinic updates securely.</p>
        </section>
        <section class="patient-login-form-panel">
            <div class="page-eyebrow">Secure access</div>
            <?php $loginSuccess = session()->getFlashdata('success'); ?>
            <?php $setupErrors = session()->getFlashdata('setup_errors') ?? []; ?>
            <?php $loginErrors = session()->getFlashdata('login_errors') ?? []; ?>
            <?php if ($loginSuccess !== null && $loginSuccess !== ''): ?><div class="alert alert-success" role="status"><?= esc($loginSuccess) ?></div><?php endif; ?>
            <?php $loginError = session()->getFlashdata('error'); ?>
            <?php if ($loginError !== null && $loginError !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= esc($loginError) ?>
                    <button class="close" type="button" data-dismiss="alert" aria-label="Dismiss error">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            <div class="patient-auth-tabs" role="tablist" aria-label="Patient account access">
                <button class="btn btn-sm patient-auth-tab" id="patient-login-tab" type="button" role="tab" aria-controls="patient-login-panel" aria-selected="true" data-patient-auth-tab="login">Login</button>
                <button class="btn btn-sm patient-auth-tab" id="patient-setup-tab" type="button" role="tab" aria-controls="patient-setup-panel" aria-selected="false" data-patient-auth-tab="setup">First-time password setup</button>
            </div>
            <section id="patient-login-panel" class="patient-auth-panel" role="tabpanel" aria-labelledby="patient-login-tab">
                <h2 id="patient-login-title" class="h4 mb-2">Patient sign in</h2>
                <p class="text-muted small mb-4">Use the email or mobile number linked to your patient account.</p>
                <form id="patient-login-form" method="post" action="<?= base_url('patient/login') ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="patient-login-identifier">Email or mobile number</label>
                        <input id="patient-login-identifier" class="form-control" type="text" name="login_identifier" value="<?= esc(old('login_identifier')) ?>" placeholder="Email or +countrycode mobile" required autocomplete="username" inputmode="text">
                        <div class="validation-error" id="login_identifierErr"><?= esc($loginErrors['login_identifier'] ?? '') ?></div>
                    </div>
                    <div class="form-group">
                        <label for="patient-login-password">Password</label>
                        <input id="patient-login-password" class="form-control" type="password" name="password" required autocomplete="current-password">
                        <div class="validation-error" id="login_passwordErr"><?= esc($loginErrors['password'] ?? '') ?></div>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
                </form>
                <a class="btn btn-link btn-block mt-3" href="<?= base_url('system/login') ?>">Staff sign in</a>
            </section>
            <section id="patient-setup-panel" class="patient-auth-panel" role="tabpanel" aria-labelledby="patient-setup-tab" hidden>
                <h2 class="h5 mb-2">Create your patient password</h2>
                <p class="small text-muted">Verify your patient details to create a password.</p>
                <form id="patient-setup-form" method="post" action="<?= base_url('patient/login/setup') ?>">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="patient-setup-country-code">Country code</label>
                            <select class="form-control btSelect" data-live-search="true" title="Select country code" name="telephone_country_code_id" id="patient-setup-country-code">
                                <option value="">Select country code</option>
                                <?php foreach ($telephoneCodes ?? [] as $telephoneCode): ?>
                                    <option value="<?= (int) $telephoneCode['id'] ?>" <?= (string) old('telephone_country_code_id') === (string) $telephoneCode['id'] ? 'selected' : '' ?>>
                                        <?= esc($telephoneCode['country_name'] ?? $telephoneCode['name'] ?? '') ?> (+<?= esc((string) $telephoneCode['normalized_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="custom-control custom-switch mt-2">
                                <input class="custom-control-input" type="checkbox" id="patient-setup-manual-code-toggle">
                                <label class="custom-control-label small" for="patient-setup-manual-code-toggle">Enter country code manually</label>
                            </div>
                            <input class="form-control mt-2" type="text" name="country_code" id="patient-setup-manual-code" placeholder="+91" inputmode="tel" maxlength="5" disabled>
                            <div class="validation-error" id="country_codeErr"><?= esc($setupErrors['country_code'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="patient-setup-mobile">Mobile number</label>
                            <input id="patient-setup-mobile" class="form-control" type="text" name="mobile_number" value="<?= esc(old('mobile_number')) ?>" inputmode="tel" required>
                            <div class="validation-error" id="mobile_numberErr"><?= esc($setupErrors['mobile_number'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="new-password">Create password</label>
                            <input id="new-password" class="form-control" type="password" name="new_password" minlength="8" required>
                            <div class="validation-error" id="passwordErr"><?= esc($setupErrors['password'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="new-password-confirmation">Confirm password</label>
                            <input id="new-password-confirmation" class="form-control" type="password" name="new_password_confirmation" minlength="8" required>
                            <div class="validation-error" id="password_confirmationErr"><?= esc($setupErrors['password_confirmation'] ?? '') ?></div>
                        </div>
                    </div>
                    <button class="btn btn-outline-primary btn-block" type="submit">Create patient password</button>
                </form>
                <button class="btn btn-link btn-block mt-3" type="button" data-patient-auth-tab="login">Back to login</button>
            </section>
        </section>
    </div>
</main>
<script>
    (function () {
        var manualCodeToggle = document.getElementById('patient-setup-manual-code-toggle');
        var manualCodeInput = document.getElementById('patient-setup-manual-code');
        var countryCodeSelect = document.getElementById('patient-setup-country-code');

        if (manualCodeToggle && manualCodeInput && countryCodeSelect) {
            manualCodeToggle.addEventListener('change', function () {
                manualCodeInput.disabled = !manualCodeToggle.checked;
                countryCodeSelect.disabled = manualCodeToggle.checked;
            });
        }

        function setPatientAuthTab(selected) {
            var showSetup = selected === 'setup';
            var loginPanel = document.getElementById('patient-login-panel');
            var setupPanel = document.getElementById('patient-setup-panel');
            var loginTab = document.getElementById('patient-login-tab');
            var setupTab = document.getElementById('patient-setup-tab');

            if (!loginPanel || !setupPanel || !loginTab || !setupTab) {
                return;
            }

            loginPanel.hidden = showSetup;
            setupPanel.hidden = !showSetup;
            Array.prototype.forEach.call(loginPanel.querySelectorAll('input, select, button'), function (control) {
                control.disabled = showSetup;
            });
            Array.prototype.forEach.call(setupPanel.querySelectorAll('input, select, button'), function (control) {
                control.disabled = !showSetup;
            });
            loginTab.setAttribute('aria-selected', showSetup ? 'false' : 'true');
            setupTab.setAttribute('aria-selected', showSetup ? 'true' : 'false');
            loginTab.classList.toggle('active', !showSetup);
            setupTab.classList.toggle('active', showSetup);

            if (showSetup && manualCodeToggle && manualCodeInput && countryCodeSelect) {
                manualCodeInput.disabled = !manualCodeToggle.checked;
                countryCodeSelect.disabled = manualCodeToggle.checked;
            }
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-patient-auth-tab]');

            if (trigger) {
                setPatientAuthTab(trigger.getAttribute('data-patient-auth-tab'));
            }
        });

        setPatientAuthTab(<?= empty($setupErrors) ? "'login'" : "'setup'" ?>);
    }());
</script>
<?= $this->endSection() ?>
