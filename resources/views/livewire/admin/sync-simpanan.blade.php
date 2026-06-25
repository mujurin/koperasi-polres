<?php

use App\Models\User;
use App\Models\SimpananWajib;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {

    public $isSyncing = false;
    public $isCompleted = false;
    public $isFetching = false;
    public $totalData = 0;
    public $processedData = 0;
    public $progress = 0;
    public $errorMessage = '';

    public function mount()
    {
        // No longer auto-fetching on mount!
        $this->isCompleted = false;
    }

    public function forceSync()
    {
        $this->isCompleted = false;
        $this->errorMessage = '';
        $this->processedData = 0;
        $this->totalData = 0;
        $this->progress = 0;
        $this->isFetching = true;

        $this->initFetch();
    }

    public function initFetch()
    {
        try {
            $response = Http::timeout(20)->get('https://siapklu.com/api/simpanan_wajib/koperasi');

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']) && is_array($data['data'])) {
                    $apiData = $data['data'];
                    $this->totalData = count($apiData);

                    if ($this->totalData > 0) {
                        Cache::put('sync_simpanan_data_' . auth()->id(), $apiData, 300);
                        $this->isFetching = false;
                        $this->isSyncing = true;
                        $this->dispatch('continue-sync-simpanan');
                    } else {
                        $this->markCompleted();
                    }
                } else {
                    $this->markError('Format data API tidak sesuai.');
                }
            } else {
                $this->markError('Gagal menghubungi API. Status: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->markError('Gagal menghubungi API: ' . $e->getMessage());
        }
    }

    #[On('continue-sync-simpanan')]
    public function processBatch()
    {
        $cacheKey = 'sync_simpanan_data_' . auth()->id();
        $apiData = Cache::get($cacheKey, []);

        if (empty($apiData)) {
            $this->markCompleted();
            return;
        }

        // Ambil 50 data untuk diproses di batch ini agar UI terlihat jalan
        $batch = array_splice($apiData, 0, 50);
        Cache::put($cacheKey, $apiData, 300);

        foreach ($batch as $anggota) {
            $nip = $anggota['nrp'] ?? null;
            $nama = $anggota['nama'] ?? $nip;

            if (!$nip)
                continue;

            // Cari atau buat user baru
            $user = User::firstOrCreate(
                ['nrp' => $nip],
                [
                    'name' => $nama,
                    'email' => $nip . '@koperasipolres.local',
                    'password' => Hash::make($nip),
                ]
            );

            $simpananList = $anggota['simpanan_wajib'] ?? [];
            if (is_array($simpananList)) {
                foreach ($simpananList as $simpanan) {
                    $bulan = $simpanan['bulan'] ?? null;
                    $tahun = $simpanan['tahun'] ?? null;
                    $jumlah = $simpanan['jumlah'] ?? 0;

                    if (!$bulan || !$tahun || $jumlah <= 0)
                        continue;

                    // Cek duplikasi Simpanan Wajib (berdasarkan user_id, bulan, tahun)
                    $wajibExists = SimpananWajib::where('user_id', $user->id)
                        ->where('bulan', $bulan)
                        ->where('tahun', $tahun)
                        ->exists();

                    if (!$wajibExists) {
                        SimpananWajib::create([
                            'user_id' => $user->id,
                            'bulan' => $bulan,
                            'tahun' => $tahun,
                            'jumlah' => $jumlah,
                            'keterangan' => 'Hasil Sinkronisasi API',
                        ]);
                    }
                }
            }
        }

        $this->processedData += count($batch);
        $this->progress = $this->totalData > 0 ? min(100, round(($this->processedData / $this->totalData) * 100)) : 100;

        if (count($apiData) > 0) {
            // Lanjut ke batch berikutnya
            $this->dispatch('continue-sync-simpanan');
        } else {
            $this->markCompleted();
            $this->dispatch('simpanan-synced'); // trigger parent view reload if needed
        }
    }

    private function markCompleted()
    {
        $this->isFetching = false;
        $this->isSyncing = false;
        $this->isCompleted = true;
        Cache::forget('sync_simpanan_data_' . auth()->id());
    }

    private function markError($msg)
    {
        $this->isFetching = false;
        $this->isSyncing = false;
        $this->errorMessage = $msg;
    }
}; ?>

<div>
    {{-- Header Sinkronisasi Manual --}}
    @if(!$isFetching && !$isSyncing)
        <div
            class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900/50 shadow-sm gap-4">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Sinkronisasi Data Simpanan</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Tarik dan telusuri data simpanan wajib terbaru
                    secara langsung dari API SIAPKLU. Proses sinkronisasi akan memakan waktu.</p>
            </div>
            <flux:button wire:click="forceSync" variant="primary" class="shrink-0" icon="arrow-path">Mulai Sinkronisasi
            </flux:button>
        </div>
    @endif

    {{-- Pesan Selesai --}}
    @if($isCompleted && empty($errorMessage))
        <div
            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-800/40 dark:bg-emerald-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                <div>
                    <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Sinkronisasi Selesai</h3>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">Pembaruan data berhasil disinkronkan
                        sepenuhnya ke tabel Koperasi PWA.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Jika sedang Fetching atau Syncing, tampilkan UI progress bar --}}
    @if($isFetching || $isSyncing)
        <div
            class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50 shadow-sm dark:border-emerald-800/40 dark:bg-emerald-900/20">
            <div class="px-5 py-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                        @if($isFetching)
                            <svg class="size-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        @else
                            <flux:icon name="arrow-path" class="size-5 animate-spin" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-emerald-900 dark:text-emerald-300">
                                @if($isFetching)
                                    Menghubungi API Data Potongan Koperasi...
                                @else
                                    Sinkronisasi Data Simpanan Wajib
                                @endif
                            </h3>
                            @if($isSyncing)
                                <span
                                    class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">{{ $progress }}%</span>
                            @endif
                        </div>
                        <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">
                            @if($isFetching)
                                Menunggu respons potongan anggota...
                            @elseif($isSyncing)
                                Memproses {{ $processedData }} dari {{ $totalData }} rekaman API...
                            @endif
                        </p>
                    </div>
                </div>

                @if($isSyncing)
                    <div class="mt-4 flex h-2 w-full overflow-hidden rounded-full bg-emerald-200 dark:bg-emerald-950">
                        <div class="flex flex-col justify-center overflow-hidden bg-emerald-600 transition-all duration-300 dark:bg-emerald-500"
                            role="progressbar" style="width: {{ $progress }}%" aria-valuenow="{{ $progress }}" aria-valuemin="0"
                            aria-valuemax="100"></div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Pesan Error jika gagal --}}
    @if($errorMessage)
        <div
            class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 dark:border-rose-800/40 dark:bg-rose-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                <div>
                    <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300">Gagal Sinkronisasi</h3>
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif
</div>