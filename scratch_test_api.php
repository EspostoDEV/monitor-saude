<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Services\InfoDengueService;
use Illuminate\Support\Facades\Http;

$service = new InfoDengueService();
$data = $service->fetch(3304557, 'dengue', 2024, 1, 2024, 10);

if (empty($data)) {
    echo "No data found or API error.\n";
} else {
    echo "Successfully fetched " . count($data) . " records.\n";
    echo "First record sample:\n";
    print_r($data[0]);
}
