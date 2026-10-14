<?php

namespace App\Modules\Shopify\Controllers;

use Admin\Models\PatientModel;
use App\Controllers\BaseController;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use App\Services\Shopify\ShopifyHandoffService;
use CodeIgniter\HTTP\RedirectResponse;
use InvalidArgumentException;

final class Handoff extends BaseController
{
    public function patientLogin(): RedirectResponse
    {
        try {
            $handoff = (new ShopifyHandoffService())->consume((string) $this->request->getGet('token'));
            $link = (new ShopifyPatientIdentityLinkModel())
                ->where('shopify_store_domain', $handoff['store_domain'])
                ->where('shopify_customer_id', $handoff['customer_id'])
                ->where('patient_id', $handoff['patient_id'])
                ->where('link_status', 'linked')
                ->first();
            $patient = (new PatientModel())
                ->where('id', $handoff['patient_id'])
                ->where('status', 1)
                ->first();

            if ($link === null || $patient === null || (int) ($patient['clinic_id'] ?? 0) < 1) {
                throw new InvalidArgumentException('Shopify patient handoff scope is invalid.');
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
                    'username' => (string) ($patient['email'] ?? ''),
                    'email' => (string) ($patient['email'] ?? ''),
                    'clinic_id' => (int) $patient['clinic_id'],
                ],
                'roles' => ['Patient'],
                'permissions' => [],
            ]);
            service('audit')->record('shopify.handoff_succeeded', [
                'store_domain' => $handoff['store_domain'],
                'customer_id' => $handoff['customer_id'],
                'patient_id' => $handoff['patient_id'],
            ]);

            return redirect()->to(base_url('patient'));
        } catch (\Throwable $exception) {
            log_message('warning', 'Shopify patient handoff failed safely.');
            service('audit')->record('shopify.handoff_failed', []);

            return redirect()->to(base_url('patient/login'))->with('error', 'The patient sign-in link is invalid or expired.');
        }
    }
}
