<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Modules/CoreApi/Config/EndpointDefinitions.php';

use App\Modules\CoreApi\Config\EndpointDefinitions;

$path = __DIR__ . '/../docs/CORE-API.md';
$document = file_get_contents($path);
$start = '<!-- BEGIN CORE API ENDPOINT CATALOG -->';
$end = '<!-- END CORE API ENDPOINT CATALOG -->';
$catalog = $start . PHP_EOL . PHP_EOL . "| Method | Path | Purpose | Required headers | Parameters | Response fields | Errors |" . PHP_EOL . "|---|---|---|---|---|---|---|" . PHP_EOL;

foreach (EndpointDefinitions::all() as $endpoint) {
    $catalog .= '| ' . $endpoint['method'] . ' | ' . $endpoint['path'] . ' | ' . $endpoint['purpose'] . ' | ' . $endpoint['headers'] . ' | ' . $endpoint['parameters'] . ' | ' . $endpoint['response_fields'] . ' | ' . $endpoint['errors'] . ' |' . PHP_EOL;
}

$catalog .= PHP_EOL . $end;
$pattern = '/<!-- BEGIN CORE API ENDPOINT CATALOG -->.*?<!-- END CORE API ENDPOINT CATALOG -->/s';
$updated = preg_replace($pattern, $catalog, $document);

if ($updated === null || $updated === $document) {
    fwrite(STDERR, "Core API endpoint catalog markers were not found.\n");
    exit(1);
}

file_put_contents($path, $updated);
