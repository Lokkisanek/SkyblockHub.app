<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app('App\Jobs\AnalyzeMarketManipulationJob')->handle();
echo "analyze_ok\n";
