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
                return service('response')->setStatusCode(403)->setJSON(['status' => false, 'message' => 'Patient accounts cannot access staff workflows.', 'msg' => 'Patient accounts cannot access staff workflows.', 'data' => [], 'error' => ['code' => 'STAFF_AUTHENTICATION_REQUIRED', 'message' => 'Patient accounts cannot access staff workflows.'], 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]);
            }

            return redirect()->to(base_url('permission-denied'));
        }

        if (!session('isLoggedIn')) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON(['status' => false, 'message' => 'Your session has expired. Please login again.', 'msg' => 'Your session has expired. Please login again.', 'data' => [], 'error' => ['code' => 'AUTHENTICATION_REQUIRED', 'message' => 'Your session has expired. Please login again.'], 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]);
            }

            return redirect()->to(base_url('system/login'))->with('error', 'Please login first.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
