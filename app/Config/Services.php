<?php

namespace Config;

use CodeIgniter\Config\BaseService;
/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function auth($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }
        return new \App\Services\AuthService();
    }
    /**
     *------------------------------------------------------------
     * Location Service
     *----------------------------------------------------------
     */
    public static function location(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('location');
        }
        return new \App\Services\LocationService();
    }
    public static function notification(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('notification');
        }
        return new \App\Services\Notification\NotificationDispatcher();
    }
    public static function audit(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('audit');
        }
        return new \App\Services\Audit\AuditLogger();
    }
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
