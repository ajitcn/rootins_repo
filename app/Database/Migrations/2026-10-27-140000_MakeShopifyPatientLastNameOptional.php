<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class MakeShopifyPatientLastNameOptional extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('patients')) return;

        $this->forge->modifyColumn('patients', [
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
    }

    public function down()
    {
        // Keep the rollback nullable so existing Shopify patients without a
        // last name are not made invalid by reverting the migration.
        if ($this->db->tableExists('patients')) {
            $this->forge->modifyColumn('patients', [
                'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            ]);
        }
    }
}
