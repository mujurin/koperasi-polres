<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Pinjaman;
use App\Models\Angsuran;
use Carbon\Carbon;

$user = User::where('nrp', '94070287')->first();
if (!$user) {
    echo "User HADITIA UTAMI IHSAN not found.\n";
    exit;
}

$pinjaman = Pinjaman::where('user_id', $user->id)
    ->where('status', 'disetujui')
    ->first();

if (!$pinjaman) {
    echo "This user doesn't have an active (disetujui) Pinjaman.\n";
    exit;
}

// Ensure the pinjaman has tenor >= 3
if ($pinjaman->tenor < 3) {
    echo "Tenor is less than 3, adjust it first.\n";
    exit;
}

// Clear existing angsurans if any
$pinjaman->angsurans()->delete();

// Create 3 paid installments (months 4, 5, 6 - April, May, June)
for ($i = 1; $i <= 3; $i++) {
    // 1 -> April, 2 -> May, 3 -> June
    $tanggalBayar = Carbon::create(2026, 3 + $i, 15);

    // Untuk Juni, bayarnya sebagian
    $jumlahBayar = ($i === 3) ? 1200000 : $pinjaman->angsuran_perbulan;

    Angsuran::create([
        'pinjaman_id' => $pinjaman->id,
        'angsuran_ke' => $i,
        'jumlah_bayar' => $jumlahBayar,
        'tanggal_bayar' => $tanggalBayar->format('Y-m-d'),
        'status_pembayaran' => 'lunas',
    ]);
}

$count = $pinjaman->angsurans()->count();
echo "Successfully created {$count} dummy angsurans for pinjaman ID {$pinjaman->id} (June has 1.2M).\n";
