<?php

namespace App\Controllers;

class PermissionDenied extends BaseController
{
    public function index()
    {
        return $this->response->setStatusCode(403)->setBody(view('errors/html/permission_denied'));
    }
}
