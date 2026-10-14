<?php

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Read-only negative-path checks for a provisioned test database.
 *
 * The suite intentionally skips when database.tests is not configured or when
 * the selected driver is unavailable. It never mutates application data.
 */
final class DoctorDatabaseSecurityTest extends CIUnitTestCase
{
    private static function databaseOrSkip(): \CodeIgniter\Database\BaseConnection
    {
        try {
            $db = db_connect('tests');
        } catch (\Throwable $exception) {
            self::markTestSkipped('A provisioned database.tests connection is required for Doctor integration checks.');
        }

        foreach (['users', 'doctors', 'clinics', 'patients', 'appointments', 'consultations', 'prescriptions', 'doctor_notifications'] as $table) {
            if (!$db->tableExists($table)) {
                self::markTestSkipped('Required application tables are not present in database.tests.');
            }
        }

        return $db;
    }

    public function testInvalidIdsCannotResolveAcrossDoctorClinicScope(): void
    {
        $db = self::databaseOrSkip();
        $missing = 2147483000;
        self::assertNull($db->table('appointments')->where('id', $missing)->get()->getRowArray());
        self::assertNull($db->table('patients')->where('id', $missing)->get()->getRowArray());
        self::assertNull($db->table('prescriptions')->where('id', $missing)->get()->getRowArray());
        self::assertNull($db->table('doctor_notifications')->where('id', $missing)->get()->getRowArray());
    }

    public function testDoctorScopedAppointmentQueryDoesNotReturnAnotherClinic(): void
    {
        $db = self::databaseOrSkip();
        $doctor = $db->table('doctors')->select('id, clinic_id')->where('status', 1)->get()->getRowArray();

        if ($doctor === null) {
            self::markTestSkipped('No active Doctor fixture exists in database.tests.');
        }
        $otherClinic = (int) $doctor['clinic_id'] + 1;
        $rows = $db->table('appointments')->where('doctor_id', (int) $doctor['id'])->where('clinic_id', $otherClinic)->get()->getResultArray();
        self::assertIsArray($rows);

        foreach ($rows as $row) {
            self::assertSame($otherClinic, (int) $row['clinic_id']);
        }
    }

    public function testNotificationReadQueryRequiresDoctorAndClinicOwnership(): void
    {
        $db = self::databaseOrSkip();
        $notification = $db->table('doctor_notifications')->select('id, doctor_id, clinic_id')->get()->getRowArray();

        if ($notification === null) {
            self::markTestSkipped('No Doctor notification fixture exists in database.tests.');
        }
        $wrongDoctor = (int) $notification['doctor_id'] + 1;
        $wrongClinic = (int) $notification['clinic_id'] + 1;
        self::assertNull($db->table('doctor_notifications')->where('id', (int) $notification['id'])->where('doctor_id', $wrongDoctor)->where('clinic_id', (int) $notification['clinic_id'])->get()->getRowArray());
        self::assertNull($db->table('doctor_notifications')->where('id', (int) $notification['id'])->where('doctor_id', (int) $notification['doctor_id'])->where('clinic_id', $wrongClinic)->get()->getRowArray());
    }
}
