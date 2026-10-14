<?php

namespace App\Filters;

use App\Services\PatientScope;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class PatientAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('patient_authenticated') || ! is_array(session('patient')) || (new PatientScope())->current() === null) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON([
                    'status' => false,
                    'msg' => 'Your patient session has expired. Please login again.',
                ]);
            }

            return redirect()->to(base_url('patient/login'))->with('error', 'Please login to the patient portal.');
        }

        if ((int) (session('patient')['id'] ?? 0) <= 0) {
            session()->destroy();

            return redirect()->to(base_url('patient/login'))->with('error', 'Patient account context is unavailable.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No response mutation is required.
    }
}
