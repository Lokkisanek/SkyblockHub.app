<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$username = $argv[1] ?? 'Lokkisanecek';
$controller = app(App\Http\Controllers\Api\SkyCryptProxyController::class);
$response = $controller->profile($username);
$data = $response->getData(true);

if (!isset($data['data']['profiles']) || !is_array($data['data']['profiles'])) {
    echo "no_profiles\n";
    exit(0);
}

$profiles = $data['data']['profiles'];
$selected = null;
foreach ($profiles as $id => $profile) {
    if (($profile['selected'] ?? false) === true) {
        $selected = $profile;
        break;
    }
}
if ($selected === null) {
    $selected = reset($profiles);
}

$items = [];
foreach (['weapons', 'equipment', 'armor'] as $section) {
    $list = $selected['data'][$section] ?? [];
    if (is_array($list)) {
        foreach ($list as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }
    }
}

$withValue = 0;
foreach ($items as $item) {
    if (($item['item_value'] ?? 0) > 0) {
        $withValue++;
    }
}

echo 'selected=' . ($selected['cute_name'] ?? 'unknown') . PHP_EOL;
echo 'total_items=' . count($items) . PHP_EOL;
echo 'items_with_value=' . $withValue . PHP_EOL;

foreach (array_slice($items, 0, 12) as $item) {
    echo ($item['name'] ?? 'unknown') . ' | ' . ($item['skyblock_id'] ?? '-') . ' | ' . (($item['item_value'] ?? null) ?? '-') . PHP_EOL;
}
