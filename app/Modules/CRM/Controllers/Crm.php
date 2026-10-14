<?php
namespace CRM\Controllers;

use Admin\Controllers\Crm as AdminCrmController;
use Admin\Services\CrmLeadService as AdminCrmLeadService;
use CRM\Services\CrmLeadService;

/**
 * CRM module controller.
 *
 * The inherited workflow is intentionally retained as the compatibility
 * implementation while route ownership and namespace move to CRM.
 */
class Crm extends AdminCrmController
{
    protected function createCrmService(): AdminCrmLeadService
    {
        return new CrmLeadService();
    }
}
