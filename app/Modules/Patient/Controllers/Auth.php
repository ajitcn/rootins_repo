<?php

namespace Patient\Controllers;

use Admin\Models\Location\TelephoneCountryCode;
use Admin\Models\PatientModel;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

final class Auth extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (session('patient_authenticated')) {
            return redirect()->to(base_url('patient'));
        }

        return view(
            PATIENT_VIEW . 'auth/login',
            [
                'telephoneCodes' => (new TelephoneCountryCode())->dropdown(),
            ],
        );
    }

    public function authenticate(): RedirectResponse
    {
        $lockUntil = (int) session('patient_login_locked_until');

        if ($lockUntil > time()) {
            return redirect()->back()->withInput()->with('error', 'Too many login attempts. Please try again shortly.');
        }

        $identifier = trim((string) $this->request->getPost('login_identifier'));
        $password = (string) $this->request->getPost('password');
        $errors = [];

        if ($identifier === '') {
            $errors['login_identifier'] = 'Enter your email or mobile number.';
        } elseif (str_contains($identifier, '@') && ! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $errors['login_identifier'] = 'Enter a valid email or mobile number.';
        } elseif (! str_contains($identifier, '@') && $this->resolveMobileIdentifier($identifier) === null) {
            $errors['login_identifier'] = 'Enter a valid mobile number with country code.';
        }

        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }

        if ($errors !== []) {
            return redirect()->back()
                ->withInput()
                ->with('login_errors', $errors)
                ->with('error', (string) reset($errors));
        }

        $patient = $this->findPatientByLoginIdentifier($identifier);

        if ($patient === null || empty($patient['password_hash']) || ! password_verify($password, (string) $patient['password_hash'])) {
            $attempts = (int) session('patient_login_attempts') + 1;

            session()->set([
                'patient_login_attempts' => $attempts,
                'patient_login_locked_until' => $attempts >= 5 ? time() + 300 : 0,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid email/mobile number or password.');
        }

        if ($patient === null || (int) ($patient['clinic_id'] ?? 0) <= 0) {
            return redirect()->back()->withInput()->with('error', 'This account is not enabled for the patient portal.');
        }

        session()->regenerate(true);
        session()->set([
            'patient_login_attempts' => 0,
            'patient_login_locked_until' => 0,
            'patient_authenticated' => true,
            'patient' => [
                'id' => (int) $patient['id'],
                'user_id' => 0,
                'actor_id' => (int) $patient['id'],
                'clinic_id' => (int) $patient['clinic_id'],
            ],
            'isLoggedIn' => true,
            'user' => [
                'id' => (int) $patient['id'],
                'username' => (string) ($patient['email'] ?? $identifier),
                'email' => (string) ($patient['email'] ?? ''),
                'clinic_id' => (int) $patient['clinic_id'],
            ],
            'roles' => ['Patient'],
            'permissions' => [],
        ]);

        return redirect()->to(base_url('patient'));
    }

    /**
     * Resolve a login identifier without revealing whether an account exists.
     * Mobile values are matched against the active database country-code list.
     *
     * @return array<string, mixed>|null
     */
    private function findPatientByLoginIdentifier(string $identifier): ?array
    {
        $model = new PatientModel();
        $query = $model
            ->select('patients.*')
            ->join('clinics', 'clinics.id = patients.clinic_id')
            ->where('patients.status', 1)
            ->where('clinics.status', 1);

        if (str_contains($identifier, '@')) {
            $patients = $query
                ->where('patients.email', strtolower(trim($identifier)))
                ->findAll();

            return count($patients) === 1 ? $patients[0] : null;
        }

        $mobile = $this->resolveMobileIdentifier($identifier);

        if ($mobile === null) {
            return null;
        }

        $query
            ->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')
            ->where('patients.mobile_normalized', $mobile['mobile'])
            ->groupStart()
            ->where('telephone_country_codes.normalized_code', $mobile['country_code'])
            ->orWhere('patients.country_code', $mobile['country_code'])
            ->groupEnd();

        $patients = $query->findAll();

        return count($patients) === 1 ? $patients[0] : null;
    }

    /**
     * Normalize a full international mobile identifier into its database parts.
     *
     * @return array{country_code: string, mobile: string}|null
     */
    private function resolveMobileIdentifier(string $identifier): ?array
    {
        $digits = service('telephoneContact')->normalizedMobile($identifier);

        if ($digits === null) {
            return null;
        }

        $countryCodes = (new TelephoneCountryCode())
            ->select('normalized_code')
            ->where('status', 1)
            ->orderBy('normalized_code', 'DESC')
            ->findAll();

        foreach ($countryCodes as $country) {
            $countryCode = (string) ($country['normalized_code'] ?? '');

            if ($countryCode === '' || ! str_starts_with($digits, $countryCode)) {
                continue;
            }

            $mobile = substr($digits, strlen($countryCode));

            if (preg_match('/^[0-9]{7,15}$/', $mobile)) {
                return [
                    'country_code' => $countryCode,
                    'mobile' => $mobile,
                ];
            }
        }

        return null;
    }

    public function setupPassword(): RedirectResponse
    {
        $lockUntil = (int) session('patient_setup_locked_until');

        if ($lockUntil > time()) {
            return redirect()->back()->withInput()->with('error', 'Too many setup attempts. Please try again shortly.');
        }

        $selectedCountryCodeId = (int) $this->request->getPost('telephone_country_code_id');
        $manualCountryCode = trim((string) $this->request->getPost('country_code'));
        $mobileNumber = trim((string) $this->request->getPost('mobile_number'));
        $password = (string) $this->request->getPost('new_password');
        $confirmation = (string) $this->request->getPost('new_password_confirmation');
        $errors = [];
        $countryCode = null;

        if ($selectedCountryCodeId > 0) {
            $country = (new TelephoneCountryCode())
                ->where('id', $selectedCountryCodeId)
                ->where('status', 1)
                ->first();

            if ($country === null) {
                $errors['country_code'] = 'Select a valid country code.';
            } else {
                $countryCode = (string) $country['normalized_code'];
            }
        } else {
            $countryCode = TelephoneCountryCode::normalizeCode($manualCountryCode);

            if ($countryCode === null || (new TelephoneCountryCode())->activeByCode($countryCode) === null) {
                $errors['country_code'] = 'Enter or select a valid country code.';
            }
        }

        $normalizedMobile = service('telephoneContact')->normalizedMobile($mobileNumber);

        if ($normalizedMobile === null || ! preg_match('/^[0-9]{7,15}$/', $normalizedMobile)) {
            $errors['mobile_number'] = 'Enter a valid mobile number.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            $this->recordSetupFailure();

            return redirect()->back()->withInput()->with('setup_errors', $errors)->with('error', (string) reset($errors));
        }

        $patients = (new PatientModel())
            ->select('patients.*')
            ->join('clinics', 'clinics.id = patients.clinic_id')
            ->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')
            ->where('patients.mobile_normalized', $normalizedMobile)
            ->groupStart()
            ->where('telephone_country_codes.normalized_code', $countryCode)
            ->orWhere('patients.country_code', $countryCode)
            ->groupEnd()
            ->where('patients.status', 1)
            ->where('clinics.status', 1)
            ->findAll();

        if ($patients === []) {
            $this->recordSetupFailure();

            return redirect()->back()->withInput()->with('error', 'The supplied patient details could not be verified.');
        }

        if (count($patients) !== 1) {
            $this->recordSetupFailure();

            return redirect()->back()->withInput()->with('error', 'The supplied mobile details match more than one patient account.');
        }

        $patient = $patients[0];

        if (! empty($patient['password_hash'])) {
            return redirect()->to(base_url('patient/login'))->with('error', 'A patient password has already been created. Please sign in.');
        }

        $db = db_connect();
        $updateFields = [
            'password_hash',
            'failed_login_attempts',
            'locked_until',
        ];
        $missingFields = array_values(
            array_filter(
                $updateFields,
                static fn (string $field): bool => ! $db->fieldExists($field, 'patients'),
            ),
        );

        if ($missingFields !== []) {
            log_message(
                'error',
                'Patient password setup schema failure route=patient/login/setup lookup=unique field_keys={field_keys}',
                [
                    'field_keys' => implode(',', $missingFields),
                ],
            );

            return redirect()->back()->withInput()->with('error', 'Password could not be created safely.');
        }

        try {
            $db->transBegin();

            $updated = (new PatientModel())->update(
                (int) $patient['id'],
                [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ],
            );

            if (! $updated || $db->transStatus() === false) {
                $db->transRollback();

                log_message(
                    'error',
                    'Patient password setup transaction failed route=patient/login/setup lookup=unique field_keys={field_keys}',
                    [
                        'field_keys' => implode(',', $updateFields),
                    ],
                );

                return redirect()->back()->withInput()->with('error', 'Password could not be created safely.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            log_message(
                'error',
                'Patient password setup exception route=patient/login/setup lookup=unique field_keys={field_keys} exception={exception} file={file} line={line}',
                [
                    'field_keys' => implode(',', $updateFields),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );

            return redirect()->back()->withInput()->with('error', 'Password could not be created safely.');
        }

        session()->set(['patient_setup_attempts' => 0, 'patient_setup_locked_until' => 0]);

        return redirect()->to(base_url('patient/login'))->with('success', 'Password created. You can now sign in.');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(base_url('patient/login'));
    }

    private function recordSetupFailure(): void
    {
        $attempts = (int) session('patient_setup_attempts') + 1;
        session()->set([
            'patient_setup_attempts' => $attempts,
            'patient_setup_locked_until' => $attempts >= 5 ? time() + 300 : 0,
        ]);
    }
}
