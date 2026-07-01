<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Pinjaman;
use App\Models\Angsuran;
use App\Models\SimpananPokok;
use App\Models\SimpananWajib;
use App\Models\Penarikan;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    public bool $confirmPinjaman = false;
    public bool $confirmSetoran = false;

    public function resetPinjaman()
    {
        if (!$this->confirmPinjaman)
            return;

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Angsuran::truncate();
        Pinjaman::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->confirmPinjaman = false;

        session()->flash('successPinjaman', 'Semua data Pinjaman dan Angsuran berhasil dikosongkan secara permanen!');
        $this->dispatch('close-modal', 'modal-reset-pinjaman');
    }

    public function resetSetoran()
    {
        if (!$this->confirmSetoran)
            return;

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SimpananPokok::truncate();
        SimpananWajib::truncate();
        Penarikan::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->confirmSetoran = false;

        session()->flash('successSetoran', 'Semua data Simpanan (Pokok, Wajib) dan Penarikan berhasil dikosongkan secara permanen!');
        $this->dispatch('close-modal', 'modal-reset-setoran');
    }
} ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Alat Manajemen Basis Data</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Kosongkan data tabel secara permanen. Alat ini berguna untuk
            membersihkan data dummy setelah masa uji coba.</p>
    </div>

    <!-- Alert for Pinjaman -->
    @if (session()->has('successPinjaman'))
        <div
            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-800/40 dark:bg-emerald-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                <div>
                    <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Berhasil</h3>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ session('successPinjaman') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Alert for Setoran -->
    @if (session()->has('successSetoran'))
        <div
            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-800/40 dark:bg-emerald-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                <div>
                    <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Berhasil</h3>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ session('successSetoran') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Kosongkan Data Pinjaman -->
        <div class="rounded-2xl border border-rose-200 bg-white p-6 shadow-sm dark:border-rose-900/50 dark:bg-zinc-900">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400">
                    <flux:icon name="banknotes" class="size-6" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-rose-900 dark:text-rose-100">Kosongkan Pinjaman</h2>
                    <p class="text-xs text-zinc-500 mt-1 dark:text-zinc-400 mb-4">
                        Menghapus seluruh rekaman dari tabel <strong>pinjamans</strong> dan <strong>angsurans</strong>.
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <flux:modal.trigger name="modal-reset-pinjaman">
                        <flux:button variant="danger" icon="trash">Reset Data Pinjaman</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </div>

        <!-- Kosongkan Data Setoran -->
        <div class="rounded-2xl border border-rose-200 bg-white p-6 shadow-sm dark:border-rose-900/50 dark:bg-zinc-900">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400">
                    <flux:icon name="wallet" class="size-6" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-rose-900 dark:text-rose-100">Kosongkan Setoran / Simpanan</h2>
                    <p class="text-xs text-zinc-500 mt-1 dark:text-zinc-400 mb-4">
                        Menghapus seluruh rekaman dari tabel <strong>simpanan_pokoks</strong>,
                        <strong>simpanan_wajibs</strong>, dan <strong>penarikans</strong>. Tindakan ini tidak dapat
                        dibatalkan.
                    </p>
                    <flux:modal.trigger name="modal-reset-setoran">
                        <flux:button variant="danger" icon="trash">Reset Data Setoran</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <flux:modal name="modal-reset-pinjaman" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Konfirmasi Reset Pinjaman</flux:heading>
                <flux:subheading>
                    <p class="mb-2">Anda akan menghapus SEMUA data di dalam tabel:</p>
                    <ul class="list-disc list-inside text-rose-600 dark:text-rose-400 mb-2">
                        <li>pinjamans</li>
                        <li>angsurans</li>
                    </ul>
                    <p>Apakah Anda yakin? Tindakan ini <strong>permanen</strong>.</p>
                </flux:subheading>
            </div>

            <flux:checkbox wire:model.live="confirmPinjaman"
                label="Ya, saya mengerti ini akan menghapus data secara permanen." />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="resetPinjaman" variant="danger" :disabled="!$confirmPinjaman">Kosongkan
                    Sekarang</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="modal-reset-setoran" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Konfirmasi Reset Setoran</flux:heading>
                <flux:subheading>
                    <p class="mb-2">Anda akan menghapus SEMUA data di dalam tabel:</p>
                    <ul class="list-disc list-inside text-rose-600 dark:text-rose-400 mb-2">
                        <li>simpanan_pokoks</li>
                        <li>simpanan_wajibs</li>
                        <li>penarikans</li>
                    </ul>
                    <p>Apakah Anda yakin? Tindakan ini <strong>permanen</strong>.</p>
                </flux:subheading>
            </div>

            <flux:checkbox wire:model.live="confirmSetoran"
                label="Ya, saya mengerti ini akan menghapus data secara permanen." />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="resetSetoran" variant="danger" :disabled="!$confirmSetoran">Kosongkan Sekarang
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>