<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
class Notifications extends BaseConfig
{
    /** Keep provider dispatch disabled until deployment review is complete. */
    public bool $enabled = false;
    public int $maxAttempts = 3;
    public int $retryDelaySeconds = 300;
    /** Provider names only; credentials must remain in deployment secrets. */
    public array $providers = ['sms' => 'twilio_messaging', 'whatsapp' => 'twilio_whatsapp', 'email' => 'sendgrid'];
}
