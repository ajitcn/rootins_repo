<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyClientCredentials extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_configurations')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('client_id_encrypted', 'shopify_configurations')) {
            $fields['client_id_encrypted'] = ['type' => 'TEXT', 'null' => true, 'after' => 'admin_api_access_token_encrypted'];
        }
        if (! $this->db->fieldExists('client_secret_encrypted', 'shopify_configurations')) {
            $fields['client_secret_encrypted'] = ['type' => 'TEXT', 'null' => true, 'after' => 'client_id_encrypted'];
        }
        if ($fields !== []) {
            $this->forge->addColumn('shopify_configurations', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('shopify_configurations')) {
            return;
        }
        foreach (['client_secret_encrypted', 'client_id_encrypted'] as $field) {
            if ($this->db->fieldExists($field, 'shopify_configurations')) {
                $this->forge->dropColumn('shopify_configurations', $field);
            }
        }
    }
}
