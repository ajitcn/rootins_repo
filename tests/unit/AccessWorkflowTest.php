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

    public function testUserListKeepsOptionalClinicUsersVisibleAndFiltersInactiveDeletedRows(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Access/Access.php');
        self::assertIsString($source);
        self::assertStringContainsString("->join('user_roles', 'user_roles.user_id = users.id', 'left')", $source);
        self::assertStringContainsString("->join('roles', 'roles.id = user_roles.role_id', 'left')", $source);
        self::assertStringContainsString("->where('users.status', 1)", $source);
        self::assertStringContainsString("->where('users.deleted_at IS NULL', null, false)", $source);
        self::assertStringContainsString("->orWhere('users.doctor_id', 0)", $source);
        self::assertStringContainsString("->orWhere('users.doctor_id IS NULL', null, false)", $source);
    }

    public function testUserCreationIsTransactionalAcrossRoleMapping(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Access/Access.php');
        self::assertIsString($source);
        self::assertStringContainsString('$db->transBegin();', $source);
        self::assertStringContainsString('$userId = $user->insert(', $source);
        self::assertStringContainsString('(new UserRole())->insert([\'user_id\' => $userId, \'role_id\' => $roleId])', $source);
        self::assertStringContainsString('$db->transRollback();', $source);
        self::assertStringContainsString('\'target_user_id\' => $userId', $source);
    }

    public function testUserListDiagnosticsDoNotIncludeCredentials(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Access/Access.php');
        self::assertIsString($source);
        self::assertStringContainsString("'role_mapping'", $source);
        self::assertStringContainsString("'clinic_mapping'", $source);
        self::assertStringContainsString("'list_query_result'", $source);
        $diagnosticStart = strpos($source, "Access user list diagnostic:");
        self::assertIsInt($diagnosticStart);
        $diagnostic = substr($source, $diagnosticStart, strpos($source, "JSON_UNESCAPED_SLASHES", $diagnosticStart) - $diagnosticStart);
        self::assertStringNotContainsString("'password' =>", $diagnostic);
        self::assertStringNotContainsString("'password_hash' =>", $diagnostic);
    }
}
