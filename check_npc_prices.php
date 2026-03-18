<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\BazaarProduct;

// Load Laravel
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all bazaar products
$products = BazaarProduct::all(['product_id', 'npc_sell_price'])->toArray();

// Create JSON with has_npc_price flag
$result = array_map(function($product) {
    return [
        'product_id' => $product['product_id'],
        'npc_sell_price' => (float) $product['npc_sell_price'],
        'has_npc_price' => (float) $product['npc_sell_price'] > 0,
    ];
}, $products);

// Sort by product_id for easier viewing
usort($result, function($a, $b) {
    return strcmp($a['product_id'], $b['product_id']);
});

// Print summary
$withPrice = count(array_filter($result, fn($item) => $item['has_npc_price']));
$withoutPrice = count($result) - $withPrice;

echo "=== BAZAAR PRODUCTS NPC PRICE CHECK ===\n";
echo "Total items: " . count($result) . "\n";
echo "With NPC price (> 0): $withPrice\n";
echo "Without NPC price (= 0): $withoutPrice\n";
echo "\n=== FULL JSON OUTPUT ===\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";

// Also save to file
file_put_contents('npc_prices_check.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\nSaved to npc_prices_check.json\n";
