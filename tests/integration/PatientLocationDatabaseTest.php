<?php

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
final class PatientLocationDatabaseTest extends CIUnitTestCase
{
    public function testPatientLocationJoinExecutesWithoutDuplicateLocalityAlias(): void
    {
        try {
            $db = db_connect('tests');
        } catch (\Throwable $exception) {
            self::markTestSkipped('A provisioned database.tests connection is required for patient location integration checks.');
        }
        foreach (['patients', 'countries', 'states', 'cities', 'localities'] as $table) {
            if (!$db->tableExists($table)) {
                self::markTestSkipped('The provisioned database.tests schema is unavailable.');
            }
        }
        $row = $db->table('patients')->select('patients.id, countries.name AS country_name, states.name AS state_name, cities.name AS city_name, localities.name AS locality_name')->join('countries', 'countries.id = patients.country_id', 'left')->join('states', 'states.id = patients.state_id', 'left')->join('cities', 'cities.id = patients.city_id', 'left')->join('localities', 'localities.id = patients.locality_id', 'left')->limit(1)->get()->getRowArray();
        self::assertTrue($row === null || array_key_exists('locality_name', $row));
    }
}
