<?php

require __DIR__ . '/bootstrap/app.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\BazaarProduct;

$products = BazaarProduct::limit(100)->pluck('product_id')->toArray();

foreach ($products as $product_id) {
    echo $product_id . PHP_EOL;
}

echo "\n\nTotal count: " . count($products);
