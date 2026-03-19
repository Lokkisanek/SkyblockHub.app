<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(App\Services\BinSniper\BinSniperValuationService::class);

$auction = [
    'item_uuid' => 'c6a6ac0a-8901-40e4-a3b8-55e08e397ab7',
    'item_name' => 'Fierce Shadow Assassin Helmet',
    'tier' => 'LEGENDARY',
    'lbin_price' => 2690000,
    'slbin_price' => 3500000,
    'liquidity_24h' => 28,
];

$basePrices = [
    1850000, 1890000, 1930000, 1980000, 2000000, 2020000, 2050000, 2100000,
    2120000, 2160000, 2200000, 5500000,
];

$overrides = [
    'Recombobulator 3000' => 5000000,
    'Growth VI' => 1500000,
];

$result = $service->analyzeAuction($auction, $basePrices, $overrides);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
