<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keys = ['J_S', 'A_B', 'G_G', 'H_G_G', 'M_N', 'M_C'];
$rows = Illuminate\Support\Facades\DB::table('bin_snapshots')
    ->select('internal_name', Illuminate\Support\Facades\DB::raw('MIN(price) as min_price'))
    ->where('recorded_at', '>=', now()->subHours(24))
    ->whereIn('internal_name', $keys)
    ->groupBy('internal_name')
    ->get();

echo 'rows=' . count($rows) . PHP_EOL;
foreach ($rows as $row) {
    echo $row->internal_name . ' | ' . $row->min_price . PHP_EOL;
}
