<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/.well-known/assetlinks.json', function () {
    $path = public_path('.well-known/assetlinks.json');
    if (file_exists($path)) {
        return response()->file($path, ['Content-Type' => 'application/json']);
    }
    return response()->json([
        [
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'com.siapklu.koperasi.twa',
                'sha256_cert_fingerprints' => [
                    'A3:B5:F9:93:78:DA:E7:BB:C4:14:8D:DA:56:15:A5:85:57:88:D4:AC:BC:92:FC:09:6E:1E:58:FD:CB:24:0D:FB'
                ],
            ],
        ],
    ]);
});

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin()
            ? redirect()->route('dashboard')
            : redirect()->route('anggota.dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    if (!auth()->user()->isAdmin()) {
        return redirect()->route('anggota.dashboard');
    }
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // Simpanan Module (Admin area)
    Volt::route('simpanan', 'simpanan.index')->name('simpanan.index');
    Volt::route('simpanan/pokok', 'simpanan.simpanan-pokok')->name('simpanan.pokok');
    Volt::route('simpanan/wajib', 'simpanan.simpanan-wajib')->name('simpanan.wajib');
    Volt::route('simpanan/penarikan', 'simpanan.penarikan')->name('simpanan.penarikan');
    Volt::route('simpanan/anggota/{user}', 'simpanan.anggota-detail')->name('simpanan.anggota.detail');
    Route::get('simpanan/penarikan/{penarikan}/kwitansi', [\App\Http\Controllers\PenarikanKwitansiController::class, 'download'])->name('penarikan.kwitansi');

    // Pinjaman Module (Admin area)
    Volt::route('pinjaman/antrian', 'pinjaman.antrian')->name('pinjaman.antrian');
    Route::get('pinjaman/rekap/download', [App\Http\Controllers\PdfController::class, 'rekapPdf'])->name('pinjaman.rekap.download');
    Volt::route('pinjaman/rekap', 'pinjaman.rekap')->name('pinjaman.rekap');
    Volt::route('pinjaman/rekap/download', 'pinjaman.rekap-pdf')->name('pinjaman.rekap.download');
    Volt::route('pinjaman/review/{pinjaman}', 'pinjaman.review')->name('pinjaman.review');
    Volt::route('pinjaman/cetak/{pinjaman}', 'pinjaman.cetak')->name('pinjaman.cetak'); // Keep for legacy/fallback
    Route::get('pinjaman/cetak/{pinjaman}/download', [App\Http\Controllers\PdfController::class, 'cetakPdf'])->name('pinjaman.cetak.download');
    Volt::route('pinjaman/daftar', 'pinjaman.index')->name('pinjaman.index');
    Volt::route('pinjaman/{pinjaman}/rincian', 'pinjaman.show')->name('pinjaman.show');
    Volt::route('pinjaman/tarik-setoran', 'pinjaman.tarik-setoran')->name('pinjaman.tarik-setoran');

    // Laporan & Akuntansi (Admin area)
    Volt::route('laporan/neraca-saldo', 'laporan.neraca-saldo')->name('laporan.neraca-saldo');
    Volt::route('laporan/laba-rugi', 'laporan.laba-rugi')->name('laporan.laba-rugi');

    // Admin Tools
    Volt::route('admin/dummy-angsuran', 'admin.dummy-angsuran')->name('admin.dummy-angsuran');
    Volt::route('admin/reset-data', 'admin.reset-data')->name('admin.reset-data');

    // Anggota (Member PWA area)
    Volt::route('anggota', 'anggota.dashboard')->name('anggota.dashboard');
    Volt::route('anggota/simpanan', 'anggota.simpanan')->name('anggota.simpanan');
    Volt::route('anggota/riwayat-setoran', 'anggota.riwayat-setoran')->name('anggota.riwayat-setoran');
    Volt::route('anggota/riwayat', 'anggota.riwayat')->name('anggota.riwayat');
    Volt::route('anggota/penarikan', 'anggota.penarikan')->name('anggota.penarikan');
    Volt::route('anggota/pinjaman', 'anggota.pinjaman')->name('anggota.pinjaman');
});

require __DIR__ . '/auth.php';
