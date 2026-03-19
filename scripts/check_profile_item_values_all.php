<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$username = $argv[1] ?? 'Lokkisanecek';
$controller = app(App\Http\Controllers\Api\SkyCryptProxyController::class);
$response = $controller->profile($username);
$data = $response->getData(true);
$profiles = $data['data']['profiles'] ?? [];

foreach ($profiles as $pid => $profile) {
    $items = [];
    foreach (['weapons', 'equipment', 'armor'] as $section) {
        foreach (($profile['data'][$section] ?? []) as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }
    }
    $withValue = 0;
    foreach ($items as $item) {
        if (($item['item_value'] ?? 0) > 0) {
            $withValue++;
        }
    }

    echo ($profile['cute_name'] ?? $pid)
        . ' selected=' . (($profile['selected'] ?? false) ? '1' : '0')
        . ' total=' . count($items)
        . ' valued=' . $withValue
        . PHP_EOL;
}
