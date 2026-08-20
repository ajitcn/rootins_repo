<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Permission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
         

        // Permission not passed in route
        if (empty($arguments) || empty($arguments[0])) {
            return;
        }

        $requiredPermission = $arguments[0];

        $permissions = session('permissions') ?? [];


        if (! in_array($requiredPermission, $permissions, true)) { 

            // AJAX Request
            if ($request->isAJAX()) {

                return service('response')->setJSON([
                    'status' => false,
                    'msg'    => 'You do not have permission to perform this action.'
                ]);

            }

            // Normal Request
            return redirect()->to(base_url('permission-denied'));
        }
    }

    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ) {
        //
    }
}