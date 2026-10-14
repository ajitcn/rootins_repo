<?php

namespace Tests\Unit;

use Admin\Controllers\Access\Access;
use Admin\Models\UserRole;
use PHPUnit\Framework\TestCase;

final class AccessWorkflowTest extends TestCase
{
    public function testAccessControllerRequiresStrongPasswords(): void
    {
        $reflection = new \ReflectionClass(Access::class);
        self::assertSame(10, $reflection->getConstant('MIN_PASSWORD_LENGTH'));
    }

    public function testAccessControllerUsesExistingPermissionForAdministration(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        self::assertNotFalse($routes);
        self::assertStringContainsString("permission:role.manage", $routes);
        self::assertStringContainsString("permission:user.create", $routes);
        self::assertStringContainsString("permission:user.edit", $routes);
        self::assertStringContainsString("Access::users", $routes);
        self::assertStringContainsString("Access::roles", $routes);
        self::assertStringContainsString("Access::permissions", $routes);
    }

    public function testUserRoleModelMatchesAutoIncrementSchema(): void
    {
        $defaults = (new \ReflectionClass(UserRole::class))->getDefaultProperties();
        self::assertTrue($defaults['useAutoIncrement'] ?? false);
    }
}
