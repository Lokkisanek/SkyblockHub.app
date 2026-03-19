<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$username = $argv[1] ?? 'Lokkisanecek';
$start = microtime(true);
$controller = app(App\Http\Controllers\Api\SkyCryptProxyController::class);
$response = $controller->profile($username);
$elapsedMs = (int) ((microtime(true) - $start) * 1000);

echo 'elapsed_ms=' . $elapsedMs . PHP_EOL;
echo 'status=' . $response->getStatusCode() . PHP_EOL;
