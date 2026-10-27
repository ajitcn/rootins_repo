<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Keep the global patient identity key unique per Shopify store, not globally. */
final class ScopeShopifyPatientIdentityKeysByStore extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_patient_identity_links')) {
            return;
        }

        $seen = [];
        $rows = $this->db->table('shopify_patient_identity_links')
            ->where('patient_id IS NOT NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $patientId = (int) ($row['patient_id'] ?? 0);
            $store = strtolower(trim((string) ($row['shopify_store_domain'] ?? '')));
            if ($patientId <= 0 || $store === '') {
                continue;
            }

            $key = 'shopify:' . hash('sha256', $store . '|' . $patientId);
            if (isset($seen[$key])) {
                // Preserve both customer records, but do not leave an
                // ambiguous global link that could block another store.
                $this->db->table('shopify_patient_identity_links')->where('id', (int) $row['id'])->update([
                    'patient_id' => null,
                    'link_status' => 'ambiguous',
                    'active_patient_key' => null,
                    'linked_at' => null,
                    'matched_by' => 'migration_conflict',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            $seen[$key] = true;
            $this->db->table('shopify_patient_identity_links')->where('id', (int) $row['id'])->update([
                'active_patient_key' => $key,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        // The previous key format was not store-safe and cannot be restored
        // without reintroducing cross-store collisions.
    }
}
