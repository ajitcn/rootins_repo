<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Auth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session('patient_authenticated')) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Patient accounts cannot access staff workflows.']);
            }

            return redirect()->to(base_url('permission-denied'));
        }

        if (!session('isLoggedIn')) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON(['status' => false, 'msg' => 'Your session has expired. Please login again.']);
            }

            return redirect()->to(base_url('system/login'))->with('error', 'Please login first.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
