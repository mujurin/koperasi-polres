<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::where('nrp', '03050892')->first();
if (!$u) {
    echo 'User tidak ditemukan';
    exit;
}

$pinjamanAktif = $u->pinjaman()->where('status', 'disetujui')->latest()->first();
if (!$pinjamanAktif) {
    echo 'Tidak ada pinjaman aktif';
    exit;
}

$jumlah = $pinjamanAktif->jumlah_ajuan + 15000000;
$tenor = $pinjamanAktif->tenor;

try {
    $id = \Illuminate\Support\Facades\DB::table('pinjaman')->insertGetId([
        'user_id' => $u->id,
        'jumlah_ajuan' => $jumlah,
        'jumlah_diterima' => 0,
        'tenor' => $tenor,
        'angsuran_perbulan' => 0,
        'keterangan' => '[Kompensasi] Dummy Test',
        'status' => 'proses', // Use proses instead of ditunda if it has enum issues
        'created_at' => '2026-03-15 10:00:00',
        'updated_at' => '2026-03-15 10:00:00',
    ]);
    echo "SUCCESS: Dummy pinjaman ID: " . $id;
} catch (\Exception $e) {
    file_put_contents('dummy_err.log', $e->getMessage());
    echo 'ERROR writing to dummy_err.log';
}
