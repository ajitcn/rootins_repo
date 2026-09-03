<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class AddAdminConfigurationForeignKeys extends Migration
{
    public function up()
    {
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'SET NULL', 'admin_master_records_clinic_fk');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL', 'admin_master_records_created_by_fk');
        $this->forge->processIndexes('admin_master_records');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'SET NULL', 'admin_setting_records_clinic_fk');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL', 'admin_setting_records_created_by_fk');
        $this->forge->processIndexes('admin_setting_records');
    }
    public function down()
    {
        $this->db->query('ALTER TABLE admin_master_records DROP FOREIGN KEY admin_master_records_clinic_fk, DROP FOREIGN KEY admin_master_records_created_by_fk');
        $this->db->query('ALTER TABLE admin_setting_records DROP FOREIGN KEY admin_setting_records_clinic_fk, DROP FOREIGN KEY admin_setting_records_created_by_fk');
    }
}
