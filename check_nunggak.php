<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\Models\User::where('nrp', '94070287')->first();
if ($u) {
    echo "User found\n";
    $ps = $u->pinjaman()->get();
    foreach ($ps as $p) {
        echo "Pinjaman ID: {$p->id} | Status: {$p->status} | Jenis: {$p->jenis_permohonan} | ACC: {$p->updated_at} | Lunas: " . $p->angsurans()->count() . "\n";
    }
}
