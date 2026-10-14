<?php

namespace Pharmacy\Services;

final class DeliveryProviderCatalog
{
    public function active(): array
    {
        return db_connect()->table('delivery_providers')->select('id, provider_name, provider_code, tracking_url_template, support_contact')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('provider_name', 'ASC')->get()->getResultArray();
    }

    public function findActive(int $id): ?array
    {
        if ($id <= 0) return null;
        return db_connect()->table('delivery_providers')->where('id', $id)->where('status', 1)->get()->getRowArray() ?: null;
    }

    public function trackingUrl(?string $template, string $reference): ?string
    {
        $template = trim((string) $template);
        if ($template === '' || trim($reference) === '') return null;
        $url = str_replace(['{{tracking_reference}}', '{tracking_reference}', '{tracking}'], rawurlencode(trim($reference)), $template);
        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
