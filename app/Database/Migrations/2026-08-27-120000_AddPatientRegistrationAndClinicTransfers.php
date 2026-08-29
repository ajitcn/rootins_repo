<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPatientRegistrationAndClinicTransfers extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('registration_number', 'patients')) {
            $this->forge->addColumn('patients', [
                'registration_number' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                    'after'      => 'id',
                ],
            ]);
        }

        $patients = $this->db->table('patients')
            ->select('id')
            ->where('registration_number IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($patients as $patient) {
            $this->db->table('patients')
                ->where('id', (int) $patient['id'])
                ->update([
                    'registration_number' => sprintf('PAT-%06d', (int) $patient['id']),
                ]);
        }

        $this->forge->modifyColumn('patients', [
            'registration_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'clinic_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => false,
            ],
            'date_of_birth' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'country_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
            ],
            'mobile_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
        ]);

        $this->forge->addUniqueKey('registration_number', 'patients_registration_number_unique');
        $this->forge->addUniqueKey(
            ['country_code', 'mobile_number'],
            'patients_mobile_number_unique'
        );
        $this->forge->processIndexes('patients');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'patient_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'from_clinic_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'to_clinic_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'transferred_at' => [
                'type' => 'DATETIME',
            ],
            'transferred_by' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('patient_id');
        $this->forge->addKey('from_clinic_id');
        $this->forge->addKey('to_clinic_id');
        $this->forge->addKey('transferred_at');
        $this->forge->addKey('transferred_by');

        $this->forge->addForeignKey(
            'patient_id',
            'patients',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'from_clinic_id',
            'clinics',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'to_clinic_id',
            'clinics',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'transferred_by',
            'users',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->forge->createTable('patient_clinic_transfers');
    }

    public function down()
    {
        $this->forge->dropTable('patient_clinic_transfers', true);

        $this->forge->dropKey('patients', 'patients_registration_number_unique');
        $this->forge->dropKey('patients', 'patients_mobile_number_unique');

        $this->forge->modifyColumn('patients', [
            'clinic_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'date_of_birth' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'country_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],
            'mobile_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
        ]);

        $this->forge->dropColumn('patients', 'registration_number');
    }
}
