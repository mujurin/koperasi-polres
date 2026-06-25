<?php

use App\Models\User;
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
        // Hanya sinkronisasi satu kali per sesi login
        if (!session('has_synced_anggota')) {
            $this->isFetching = true; // Munculkan UI bahwa sedang nembak API
        } else {
            $this->isCompleted = true; // Sudah sync di sesi ini
        }
    }

    public function initFetch()
    {
        if (session('has_synced_anggota')) {
            return;
        }

        try {
            $response = Http::timeout(15)->get('https://siapklu.com/api/anggota');

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']) && is_array($data['data'])) {
                    $apiData = $data['data'];
                    $this->totalData = count($apiData);

                    if ($this->totalData > 0) {
                        Cache::put('sync_anggota_data_' . auth()->id(), $apiData, 300);
                        $this->isFetching = false;
                        $this->isSyncing = true;
                        $this->dispatch('continue-sync');
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

    #[On('continue-sync')]
    public function processBatch()
    {
        $cacheKey = 'sync_anggota_data_' . auth()->id();
        $apiData = Cache::get($cacheKey, []);

        if (empty($apiData)) {
            $this->markCompleted();
            return;
        }

        // Ambil 25 data untuk diproses di batch ini agar UI terlihat jalan
        $batch = array_splice($apiData, 0, 25);
        Cache::put($cacheKey, $apiData, 300);

        foreach ($batch as $anggota) {
            $nip = $anggota['nip'] ?? null;
            if (!$nip)
                continue;

            $userExists = User::where('nrp', $nip)->exists();
            if (!$userExists) {
                User::create([
                    'nrp' => $nip,
                    'name' => $anggota['nmpeg'] ?? $nip,
                    'email' => $nip . '@koperasipolres.local',
                    'password' => Hash::make($nip),
                ]);
            }
        }

        $this->processedData += count($batch);
        $this->progress = $this->totalData > 0 ? round(($this->processedData / $this->totalData) * 100) : 100;

        if (count($apiData) > 0) {
            // Lanjut ke batch berikutnya
            $this->dispatch('continue-sync');
        } else {
            $this->markCompleted();
        }
    }

    private function markCompleted()
    {
        $this->isFetching = false;
        $this->isSyncing = false;
        $this->isCompleted = true;
        session(['has_synced_anggota' => true]);
        Cache::forget('sync_anggota_data_' . auth()->id());
    }

    private function markError($msg)
    {
        $this->isFetching = false;
        $this->isSyncing = false;
        $this->errorMessage = $msg;
        // Tetap set true agar tidak terus-terusan mengulang kalau memang error server-side
        session(['has_synced_anggota' => true]);
    }
}; ?>

<div>
    {{-- Jika belum selesai sync, tampilkan UI progress bar --}}
    @if(!$isCompleted && empty($errorMessage))

        <div wire:init="initFetch"
            class="mb-6 overflow-hidden rounded-2xl border border-indigo-200 bg-indigo-50 shadow-sm dark:border-indigo-800/40 dark:bg-indigo-900/20">
            <div class="px-5 py-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400">
                        @if($isFetching)
                            <svg class="size-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        @else
                            <flux:icon name="server" class="size-5" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-indigo-900 dark:text-indigo-300">
                                @if($isFetching)
                                    Menghubungi API SiapKLU...
                                @else
                                    Sinkronisasi Data Anggota Koperasi
                                @endif
                            </h3>
                            @if($isSyncing)
                                <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-400">{{ $progress }}%</span>
                            @endif
                        </div>
                        <p class="text-xs text-indigo-600 dark:text-indigo-500 mt-0.5">
                            @if($isFetching)
                                Menunggu repson data pengguna...
                            @elseif($isSyncing)
                                Memproses {{ $processedData }} dari {{ $totalData }} anggota...
                            @endif
                        </p>
                    </div>
                </div>

                @if($isSyncing)
                    <div class="mt-4 flex h-2 w-full overflow-hidden rounded-full bg-indigo-200 dark:bg-indigo-950">
                        <div class="flex flex-col justify-center overflow-hidden bg-indigo-600 transition-all duration-300 dark:bg-indigo-500"
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