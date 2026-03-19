<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$term = $argv[1] ?? 'JUJU';
$rows = Illuminate\Support\Facades\DB::table('bin_snapshots')
    ->select('item_name', 'internal_name', 'price')
    ->where('item_name', 'like', '%' . $term . '%')
    ->orderBy('price')
    ->limit(30)
    ->get();

foreach ($rows as $row) {
    echo $row->item_name . ' | ' . $row->internal_name . ' | ' . $row->price . PHP_EOL;
}
