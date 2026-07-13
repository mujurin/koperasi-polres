<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = \App\Models\Pinjaman::find(4);
$p->status = 'disetujui';
$p->keterangan = 'renov';
$p->save();

\App\Models\Angsuran::where('pinjaman_id', 4)->where('angsuran_ke', 999)->delete();

echo "Pinjaman 4 dikembalikan ke disetujui.";
