<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyWebhookCustomerPayload extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_webhook_events') && ! $this->db->fieldExists('payload_json', 'shopify_webhook_events')) {
            $this->forge->addColumn('shopify_webhook_events', [
                'payload_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('shopify_webhook_events') && $this->db->fieldExists('payload_json', 'shopify_webhook_events')) {
            $this->forge->dropColumn('shopify_webhook_events', 'payload_json');
        }
    }
}
