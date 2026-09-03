<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
final class Profile extends BaseController
{
    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $context = $this->context();
        return $context === null ? redirect()->to(base_url('permission-denied')) : view(DOCTOR_VIEW . 'pages/profile', ['doctor' => $context['doctor']]);
    }
    public function update(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $context = $this->context();
        if ($context === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Doctor scope is invalid.']);
        }
        $data = ['mobile' => trim((string) $this->request->getPost('mobile')), 'email' => trim((string) $this->request->getPost('email')), 'address' => trim((string) $this->request->getPost('address'))];
        $errors = [];
        if ($data['mobile'] !== '' && strlen($data['mobile']) > 20) {
            $errors['mobile'] = 'Mobile number is too long.';
        }
        if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 150)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (strlen($data['address']) > 2000) {
            $errors['address'] = 'Address is too long.';
        }
        if ($errors !== []) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => reset($errors), 'errors' => $errors]);
        }
        if (!(new DoctorModel())->update($context['doctor']['id'], $data)) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Profile could not be updated safely.']);
        }
        service('audit')->record('doctor.profile_updated', ['doctor_id' => $context['doctor']['id'], 'clinic_id' => $context['clinic_id']]);
        return $this->response->setJSON(['status' => true, 'msg' => 'Profile updated successfully.']);
    }
    private function context(): ?array
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return null;
        }
        $user = (new User())->select('doctor_id')->where('id', (int) (session('user')['id'] ?? 0))->where('status', 1)->first();
        $doctor = (new DoctorModel())->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();
        return $doctor && (int) $doctor['clinic_id'] > 0 ? ['clinic_id' => (int) $doctor['clinic_id'], 'doctor' => $doctor] : null;
    }
}
