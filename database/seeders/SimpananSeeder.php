<?php

namespace Database\Seeders;

use App\Models\Penarikan;
use App\Models\SimpananPokok;
use App\Models\SimpananWajib;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SimpananSeeder extends Seeder
{
    /**
     * Seed data dummy anggota koperasi beserta simpanan mereka.
     */
    public function run(): void
    {
        // ── Data anggota dummy ─────────────────────────────────────────
        $anggota = [
            ['name' => 'Budi Santoso', 'nrp' => '851020011', 'email' => 'budi@polres.local'],
            ['name' => 'Siti Rahayu', 'nrp' => '851020022', 'email' => 'siti@polres.local'],
            ['name' => 'Agus Prasetyo', 'nrp' => '851020033', 'email' => 'agus@polres.local'],
            ['name' => 'Dewi Kurniawati', 'nrp' => '851020044', 'email' => 'dewi@polres.local'],
            ['name' => 'Hendra Wijaya', 'nrp' => '851020055', 'email' => 'hendra@polres.local'],
            ['name' => 'Rina Marlina', 'nrp' => '851020066', 'email' => 'rina@polres.local'],
            ['name' => 'Eko Susanto', 'nrp' => '851020077', 'email' => 'eko@polres.local'],
            ['name' => 'Fitri Handayani', 'nrp' => '851020088', 'email' => 'fitri@polres.local'],
        ];

        foreach ($anggota as $data) {
            User::updateOrCreate(
                ['nrp' => $data['nrp']],
                [
                    'name' => $data['name'],
                    'nrp' => $data['nrp'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['nrp']),
                ]
            );
        }

        // ── Ambil users yang baru dibuat ──────────────────────────────
        $users = User::whereIn('nrp', array_column($anggota, 'nrp'))->get()->keyBy('nrp');

        // ── Helper: tambah simpanan wajib dari bulan awal s/d bulan ini ─
        $addWajib = function (User $user, int $startBulan, int $startTahun, int $jumlah, ?int $stopBulan = null, ?int $stopTahun = null) {
            $bulan = $startBulan;
            $tahun = $startTahun;
            $now = now();
            $endB = $stopBulan ?? (int) $now->format('n');
            $endY = $stopTahun ?? (int) $now->format('Y');

            while (
                $tahun < $endY ||
                ($tahun === $endY && $bulan <= $endB)
            ) {
                SimpananWajib::updateOrCreate(
                    ['user_id' => $user->id, 'bulan' => $bulan, 'tahun' => $tahun],
                    ['jumlah' => $jumlah, 'keterangan' => 'Potong gaji']
                );
                if ($bulan === 12) {
                    $bulan = 1;
                    $tahun++;
                } else {
                    $bulan++;
                }
            }
        };

        // ══════════════════════════════════════════════════════════════
        // 1. BUDI SANTOSO — anggota lama, pokok sudah, wajib 6 bulan, ada penarikan
        // ══════════════════════════════════════════════════════════════
        $budi = $users['851020011'];
        SimpananPokok::updateOrCreate(
            ['user_id' => $budi->id],
            ['jumlah' => 500000, 'tanggal' => '2026-01-05', 'keterangan' => 'Simpanan pokok awal']
        );
        $addWajib($budi, 1, 2026, 100000);
        Penarikan::updateOrCreate(
            ['user_id' => $budi->id, 'tanggal' => '2026-03-20'],
            ['jumlah' => 150000, 'keterangan' => 'Keperluan darurat']
        );

        // ══════════════════════════════════════════════════════════════
        // 2. SITI RAHAYU — anggota aktif, pokok sudah, wajib 6 bulan, tanpa penarikan
        // ══════════════════════════════════════════════════════════════
        $siti = $users['851020022'];
        SimpananPokok::updateOrCreate(
            ['user_id' => $siti->id],
            ['jumlah' => 500000, 'tanggal' => '2026-01-10', 'keterangan' => '']
        );
        $addWajib($siti, 1, 2026, 150000);

        // ══════════════════════════════════════════════════════════════
        // 3. AGUS PRASETYO — masuk tengah tahun, pokok sudah, wajib mulai Maret
        // ══════════════════════════════════════════════════════════════
        $agus = $users['851020033'];
        SimpananPokok::updateOrCreate(
            ['user_id' => $agus->id],
            ['jumlah' => 500000, 'tanggal' => '2026-03-01', 'keterangan' => '']
        );
        $addWajib($agus, 3, 2026, 100000);
        Penarikan::updateOrCreate(
            ['user_id' => $agus->id, 'tanggal' => '2026-05-15'],
            ['jumlah' => 200000, 'keterangan' => 'Biaya sekolah anak']
        );
        Penarikan::updateOrCreate(
            ['user_id' => $agus->id, 'tanggal' => '2026-06-01'],
            ['jumlah' => 100000, 'keterangan' => 'Keperluan rumah tangga']
        );

        // ══════════════════════════════════════════════════════════════
        // 4. DEWI KURNIAWATI — pokok sudah, setoran wajib besar (200k), penarikan besar
        // ══════════════════════════════════════════════════════════════
        $dewi = $users['851020044'];
        SimpananPokok::updateOrCreate(
            ['user_id' => $dewi->id],
            ['jumlah' => 750000, 'tanggal' => '2026-01-15', 'keterangan' => 'Simpanan pokok']
        );
        $addWajib($dewi, 1, 2026, 200000);
        Penarikan::updateOrCreate(
            ['user_id' => $dewi->id, 'tanggal' => '2026-04-10'],
            ['jumlah' => 500000, 'keterangan' => 'Renovasi rumah']
        );

        // ══════════════════════════════════════════════════════════════
        // 5. HENDRA WIJAYA — anggota baru (April), belum ada penarikan
        // ══════════════════════════════════════════════════════════════
        $hendra = $users['851020055'];
        SimpananPokok::updateOrCreate(
            ['user_id' => $hendra->id],
            ['jumlah' => 500000, 'tanggal' => '2026-04-01', 'keterangan' => '']
        );
        $addWajib($hendra, 4, 2026, 100000);

        // ══════════════════════════════════════════════════════════════
        // 6. RINA MARLINA — hanya punya simpanan wajib, belum pokok
        // ══════════════════════════════════════════════════════════════
        $rina = $users['851020066'];
        $addWajib($rina, 2, 2026, 100000);

        // ══════════════════════════════════════════════════════════════
        // 7. EKO SUSANTO — pokok + wajib banyak + 3 penarikan
        // ══════════════════════════════════════════════════════════════
        $eko = $users['851020077'];
        SimpananPokok::updateOrCreate(
            ['user_id' => $eko->id],
            ['jumlah' => 500000, 'tanggal' => '2026-01-02', 'keterangan' => '']
        );
        $addWajib($eko, 1, 2026, 125000);
        Penarikan::updateOrCreate(
            ['user_id' => $eko->id, 'tanggal' => '2026-02-12'],
            ['jumlah' => 100000, 'keterangan' => 'Cicilan motor']
        );
        Penarikan::updateOrCreate(
            ['user_id' => $eko->id, 'tanggal' => '2026-04-05'],
            ['jumlah' => 125000, 'keterangan' => 'Biaya berobat']
        );
        Penarikan::updateOrCreate(
            ['user_id' => $eko->id, 'tanggal' => '2026-06-10'],
            ['jumlah' => 50000, 'keterangan' => 'Keperluan lain']
        );

        // ══════════════════════════════════════════════════════════════
        // 8. FITRI HANDAYANI — anggota baru Juni, pokok saja belum ada wajib bulan ini
        // ══════════════════════════════════════════════════════════════
        $fitri = $users['851020088'];
        SimpananPokok::updateOrCreate(
            ['user_id' => $fitri->id],
            ['jumlah' => 500000, 'tanggal' => '2026-06-01', 'keterangan' => 'Pendaftaran']
        );
        // Hanya satu setoran wajib (Juni)
        SimpananWajib::updateOrCreate(
            ['user_id' => $fitri->id, 'bulan' => 6, 'tahun' => 2026],
            ['jumlah' => 100000, 'keterangan' => 'Setoran perdana']
        );

        $this->command->info('✅ SimpananSeeder: ' . count($anggota) . ' anggota + data simpanan berhasil dibuat.');
    }
}
