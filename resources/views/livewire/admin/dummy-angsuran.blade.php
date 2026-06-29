<?php

use App\Models\User;
use App\Models\Pinjaman;
use App\Models\Angsuran;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $nrp = '';
    
    public string $startMonth = '';
    public string $startYear = '';
    public string $endMonth = '';
    public string $endYear = '';
    
    public bool $resetExisting = true;
    public string $statusPembayaran = 'lunas';

    // Status Fields
    public ?int $userId = null;
    public string $userName = '';
    public ?int $loanId = null;
    public float $loanAmount = 0;
    public int $loanTenor = 0;
    public float $loanInstallment = 0;
    public int $existingInstallmentsCount = 0;
    public string $errorUserMsg = '';
    
    // Quick List Search
    public string $searchUser = '';

    public function mount()
    {
        $this->startMonth = (string) Carbon::now()->month;
        $this->startYear = (string) Carbon::now()->year;
        $this->endMonth = (string) Carbon::now()->month;
        $this->endYear = (string) Carbon::now()->year;
    }

    public function updatedNrp()
    {
        $this->checkNrp();
    }

    public function selectUser($nrp)
    {
        $this->nrp = $nrp;
        $this->checkNrp();
    }

    public function checkNrp()
    {
        $this->resetUserFields();

        if (empty($this->nrp)) {
            return;
        }

        $user = User::where('nrp', $this->nrp)->first();

        if (!$user) {
            $this->errorUserMsg = "Anggota dengan NRP {$this->nrp} tidak ditemukan.";
            return;
        }

        $this->userId = $user->id;
        $this->userName = $user->name;

        $pinjaman = Pinjaman::where('user_id', $user->id)
            ->where('status', 'disetujui')
            ->first();

        if (!$pinjaman) {
            $this->errorUserMsg = "Anggota {$user->name} tidak memiliki pinjaman aktif (disetujui).";
            return;
        }

        $this->loanId = $pinjaman->id;
        $this->loanAmount = (float) $pinjaman->jumlah_ajuan;
        $this->loanTenor = $pinjaman->tenor;
        $this->loanInstallment = (float) $pinjaman->angsuran_perbulan;
        $this->existingInstallmentsCount = $pinjaman->angsurans()->count();
        $this->errorUserMsg = '';
    }

    private function resetUserFields()
    {
        $this->userId = null;
        $this->userName = '';
        $this->loanId = null;
        $this->loanAmount = 0;
        $this->loanTenor = 0;
        $this->loanInstallment = 0;
        $this->existingInstallmentsCount = 0;
        $this->errorUserMsg = '';
    }

    public function generateDummyLoan()
    {
        if (!$this->userId) return;

        $user = User::find($this->userId);
        if (!$user) return;

        $pinjaman = Pinjaman::create([
            'user_id' => $user->id,
            'jumlah_ajuan' => 12000000,
            'tenor' => 12,
            'jasa_persen' => 1.00,
            'jumlah_diterima' => 12000000,
            'angsuran_perbulan' => 1120000,
            'status' => 'disetujui',
            'keterangan' => 'Pinjaman dummy otomatis untuk demo',
            'jenis_permohonan' => 'Biasa',
        ]);

        session()->flash('success', 'Pinjaman dummy berhasil dibuat. Silakan lanjutkan mengisi angsuran.');
        $this->checkNrp();
    }

    public function generate()
    {
        $this->validate([
            'nrp' => 'required',
            'startMonth' => 'required|integer|between:1,12',
            'startYear' => 'required|integer|min:2000|max:2100',
            'endMonth' => 'required|integer|between:1,12',
            'endYear' => 'required|integer|min:2000|max:2100',
            'statusPembayaran' => 'required|in:tertunda,lunas',
        ]);

        $this->checkNrp();

        if (!$this->userId || !$this->loanId) {
            session()->flash('error', 'Silakan pastikan NRP valid dan memiliki pinjaman aktif.');
            return;
        }

        $startDate = Carbon::create($this->startYear, $this->startMonth, 25)->startOfDay();
        $endDate = Carbon::create($this->endYear, $this->endMonth, 25)->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            session()->flash('error', 'Bulan/Tahun mulai harus sebelum atau sama dengan Bulan/Tahun selesai.');
            return;
        }

        $pinjaman = Pinjaman::find($this->loanId);

        if ($this->resetExisting) {
            $pinjaman->angsurans()->delete();
        }

        $currentDate = $startDate->copy();
        $count = 0;
        
        $existingCount = $this->resetExisting ? 0 : $pinjaman->angsurans()->count();

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $angsuranKe = $existingCount + $count + 1;
            $tanggalBayar = $currentDate->copy();

            Angsuran::create([
                'pinjaman_id' => $pinjaman->id,
                'angsuran_ke' => $angsuranKe,
                'jumlah_bayar' => $pinjaman->angsuran_perbulan,
                'tanggal_bayar' => $tanggalBayar->format('Y-m-d'),
                'status_pembayaran' => $this->statusPembayaran,
            ]);

            $count++;
            $currentDate->addMonth();
        }

        $this->checkNrp();
        session()->flash('success', "Berhasil menambahkan {$count} data angsuran dummy secara otomatis.");
    }

    public function with(): array
    {
        // Query users for the quick list
        $query = User::orderBy('name');
        
        if (!empty($this->searchUser)) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchUser . '%')
                  ->orWhere('nrp', 'like', '%' . $this->searchUser . '%');
            });
        }
        
        $users = $query->take(15)->get();

        // Check active loan status for each user in the list
        $usersWithLoanStatus = $users->map(function($u) {
            $hasActiveLoan = Pinjaman::where('user_id', $u->id)
                ->where('status', 'disetujui')
                ->exists();
            $u->hasActiveLoan = $hasActiveLoan;
            return $u;
        });

        return [
            'quickUsers' => $usersWithLoanStatus,
            'months' => [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ],
            'years' => range(Carbon::now()->year - 2, Carbon::now()->year + 3)
        ];
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Generator Angsuran Dummy</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Tambahkan data angsuran secara cepat untuk kebutuhan demonstrasi aplikasi.</p>
    </div>

    @if (session()->has('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-800/40 dark:bg-emerald-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                <div>
                    <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Berhasil</h3>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 dark:border-rose-800/40 dark:bg-rose-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                <div>
                    <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300">Gagal</h3>
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Form Panel --}}
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <form wire:submit="generate" class="space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Konfigurasi Data</h2>
                        <p class="text-xs text-zinc-500">Masukkan NRP anggota dan tentukan rentang bulan untuk diisi angsuran.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 items-end">
                        <div class="flex-1">
                            <flux:input wire:model.live.debounce.500ms="nrp" size="lg" label="NRP Anggota" placeholder="Ketik NRP anggota..." required />
                        </div>
                        <div class="pb-1 sm:pb-2">
                            <flux:button wire:click="checkNrp" size="sm" variant="filled" icon="magnifying-glass" class="w-full sm:w-auto">Periksa</flux:button>
                        </div>
                    </div>

                    {{-- User Verification Status --}}
                    @if($nrp && $errorUserMsg)
                        <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 dark:border-rose-900/30 dark:bg-rose-950/20">
                            <div class="flex items-start gap-3">
                                <flux:icon name="exclamation-circle" class="size-5 text-rose-500 mt-0.5" />
                                <div>
                                    <p class="text-sm font-semibold text-rose-700 dark:text-rose-400">Peringatan</p>
                                    <p class="text-xs text-rose-600 dark:text-rose-500 mt-1">{{ $errorUserMsg }}</p>
                                    
                                    @if($userId && !$loanId)
                                        <div class="mt-3">
                                            <flux:button wire:click="generateDummyLoan" size="sm" variant="primary">Buat Pinjaman Dummy Otomatis</flux:button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @elseif($nrp && $loanId)
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-900/30 dark:bg-indigo-950/20">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400">
                                    <flux:icon name="user" class="size-5" />
                                </div>
                                <div class="w-full">
                                    <h4 class="text-sm font-bold text-indigo-900 dark:text-indigo-100">{{ $userName }}</h4>
                                    
                                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-4">
                                        <div>
                                            <p class="text-[10px] text-indigo-500/80 uppercase font-semibold">Nominal Pinjaman</p>
                                            <p class="text-xs font-medium text-indigo-800 dark:text-indigo-300">Rp {{ number_format($loanAmount, 0, ',', '.') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-indigo-500/80 uppercase font-semibold">Tenor</p>
                                            <p class="text-xs font-medium text-indigo-800 dark:text-indigo-300">{{ $loanTenor }} Bulan</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-indigo-500/80 uppercase font-semibold">Angsuran / Bln</p>
                                            <p class="text-xs font-medium text-indigo-800 dark:text-indigo-300">Rp {{ number_format($loanInstallment, 0, ',', '.') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-indigo-500/80 uppercase font-semibold">Telah Dibayar</p>
                                            <p class="text-xs font-medium text-indigo-800 dark:text-indigo-300">{{ $existingInstallmentsCount }} Kali</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <fieldset class="space-y-4 rounded-xl border border-zinc-100 p-4 dark:border-zinc-800">
                            <legend class="text-xs font-semibold uppercase text-zinc-500 px-1">Mulai Dari</legend>
                            <flux:select wire:model="startMonth" label="Bulan" required>
                                @foreach($months as $val => $label)
                                    <flux:select.option value="{{ $val }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            
                            <flux:select wire:model="startYear" label="Tahun" required>
                                @foreach($years as $yr)
                                    <flux:select.option value="{{ $yr }}">{{ $yr }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </fieldset>
                        
                        <fieldset class="space-y-4 rounded-xl border border-zinc-100 p-4 dark:border-zinc-800">
                            <legend class="text-xs font-semibold uppercase text-zinc-500 px-1">Sampai Dengan</legend>
                            <flux:select wire:model="endMonth" label="Bulan" required>
                                @foreach($months as $val => $label)
                                    <flux:select.option value="{{ $val }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            
                            <flux:select wire:model="endYear" label="Tahun" required>
                                @foreach($years as $yr)
                                    <flux:select.option value="{{ $yr }}">{{ $yr }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </fieldset>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                        <flux:select wire:model="statusPembayaran" label="Status Angsuran" required>
                            <flux:select.option value="lunas">Lunas</flux:select.option>
                            <flux:select.option value="tertunda">Tertunda</flux:select.option>
                        </flux:select>
                        
                        <div class="pb-1">
                            <flux:checkbox wire:model="resetExisting" label="Hapus angsuran sebelumnya (Reset)" description="Jika dicentang, semua angsuran dari pinjaman ini akan dihapus sebelum digenerate ulang." />
                        </div>
                    </div>

                    <flux:button type="submit" variant="primary" icon="sparkles" class="w-full" :disabled="!$loanId">
                        Generate Angsuran Dummy
                    </flux:button>
                </form>
            </div>
        </div>

        {{-- Right Panel: Member Quick List --}}
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden flex flex-col h-full max-h-[800px]">
                <div class="p-5 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Daftar Anggota</h3>
                    <p class="text-[10px] text-zinc-500 mt-1 mb-4">Klik anggota untuk mengisi form secara otomatis.</p>
                    
                    <flux:input wire:model.live.debounce.300ms="searchUser" size="sm" icon="magnifying-glass" placeholder="Cari nama / NRP..." clearable />
                </div>
                
                <div class="overflow-y-auto flex-1 p-2">
                    @forelse($quickUsers as $u)
                        <button wire:click="selectUser('{{ $u->nrp }}')" type="button" class="w-full flex items-center justify-between px-3 py-3 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors text-left {{ $nrp === $u->nrp ? 'bg-indigo-50/50 dark:bg-indigo-900/20' : '' }}">
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-200">{{ $u->name }}</p>
                                <p class="text-[10px] font-mono text-zinc-500">{{ $u->nrp }}</p>
                            </div>
                            @if($u->hasActiveLoan)
                                <span title="Memiliki Pinjaman Aktif" class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    <flux:icon name="banknotes" class="size-3" />
                                </span>
                            @else
                                <span title="Tidak ada Pinjaman Aktif" class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                                    <flux:icon name="x-mark" class="size-3" />
                                </span>
                            @endif
                        </button>
                    @empty
                        <div class="p-5 text-center text-zinc-500 text-xs">
                            Tidak ada anggota ditemukan.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
