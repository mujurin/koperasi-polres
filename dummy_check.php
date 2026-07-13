<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::where('nrp', '03050892')->first();
$p = $u->pinjaman()->get(['id', 'status', 'keterangan']);
file_put_contents('dummy_log.txt', json_encode($p, JSON_PRETTY_PRINT));
