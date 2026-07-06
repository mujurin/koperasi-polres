<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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

    // Pinjaman Module (Admin area)
    Volt::route('pinjaman/antrian', 'pinjaman.antrian')->name('pinjaman.antrian');
    Volt::route('pinjaman/review/{pinjaman}', 'pinjaman.review')->name('pinjaman.review');
    Volt::route('pinjaman/cetak/{pinjaman}', 'pinjaman.cetak')->name('pinjaman.cetak');
    Volt::route('pinjaman/daftar', 'pinjaman.index')->name('pinjaman.index');
    Volt::route('pinjaman/{pinjaman}/rincian', 'pinjaman.show')->name('pinjaman.show');

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
