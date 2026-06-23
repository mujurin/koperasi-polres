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

    // Anggota (Member PWA area)
    Volt::route('anggota', 'anggota.dashboard')->name('anggota.dashboard');
    Volt::route('anggota/simpanan', 'anggota.simpanan')->name('anggota.simpanan');
    Volt::route('anggota/riwayat', 'anggota.riwayat')->name('anggota.riwayat');
    Volt::route('anggota/penarikan', 'anggota.penarikan')->name('anggota.penarikan');
    Volt::route('anggota/pinjaman', 'anggota.pinjaman')->name('anggota.pinjaman');
});

require __DIR__ . '/auth.php';
