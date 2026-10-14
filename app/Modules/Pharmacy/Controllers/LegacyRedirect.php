<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

final class LegacyRedirect extends BaseController
{
    public function index(): RedirectResponse
    {
        return $this->redirectToPharmacy();
    }

    public function path(string $path = ''): RedirectResponse
    {
        return $this->redirectToPharmacy($path);
    }

    private function redirectToPharmacy(string $path = ''): RedirectResponse
    {
        $requestPath = $this->request->getUri()->getPath();
        $targetPath = preg_replace('#/farmassist(?=/|$)#i', '/pharmacy', $requestPath) ?: '/pharmacy';
        $query = $this->request->getUri()->getQuery();

        return redirect()->to($targetPath . ($query !== '' ? '?' . $query : ''), 307);
    }
}
