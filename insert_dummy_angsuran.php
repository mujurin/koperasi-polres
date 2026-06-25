<?php

use Illuminate\Support\Facades\Artisan;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Pinjaman;
use App\Models\Angsuran;
use Carbon\Carbon;

$nrp = '94070287';
$user = User::where('nrp', $nrp)->first();

if (!$user) {
    echo "User with NRP $nrp not found.\n";
    exit;
}

$pinjaman = Pinjaman::where('user_id', $user->id)
    ->where('status', 'disetujui')
    ->first();

if (!$pinjaman) {
    echo "Active loan (disetujui) for User NRP $nrp not found.\n";
    exit;
}

$jumlahAngsuranPokok = $pinjaman->jumlah_ajuan / $pinjaman->tenor;
$jasa = $pinjaman->jumlah_ajuan * 0.01;
$totalAngsuran = $jumlahAngsuranPokok + $jasa;

echo "Found active loan ID {$pinjaman->id}. Injecting 10 dummy installments...\n";

// Count existing installments to avoid duplication or wrong starting month
$existingCount = Angsuran::where('pinjaman_id', $pinjaman->id)->count();

for ($i = 1; $i <= 10; $i++) {
    $angsuranKe = $existingCount + $i;
    $date = Carbon::now()->subMonths(10 - $i + 1)->startOfMonth()->addDays(24); // Assuming payment around 25th

    Angsuran::updateOrCreate([
        'pinjaman_id' => $pinjaman->id,
        'angsuran_ke' => $angsuranKe,
    ], [
        'jumlah_bayar' => $totalAngsuran,
        'tanggal_bayar' => $date,
        'keterangan' => 'Dummy angsuran otomatis via script',
        'created_at' => $date,
        'updated_at' => $date,
    ]);

    echo "Inserted angsuran ke-$angsuranKe untuk bulan {$date->format('M Y')}\n";
}

echo "Done!\n";
