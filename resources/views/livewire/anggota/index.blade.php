<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Data Anggota</flux:heading>
            <flux:subheading>Daftar anggota yang tersinkronisasi dengan API SIAPKLU.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari NIP atau Nama..." class="w-full sm:w-64" />
            
            <button wire:click="syncData" wire:loading.attr="disabled" wire:target="syncData"
                class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/50 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 shadow-sm transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                <flux:icon wire:loading.remove wire:target="syncData" name="arrow-path" class="size-4 text-indigo-600 dark:text-indigo-400" />
                <svg wire:loading wire:target="syncData" class="size-4 animate-spin text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="syncData">Sinkronkan Data API</span>
                <span wire:loading wire:target="syncData">Menyinkronkan...</span>
            </button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-950">
        <table class="min-w-full border-collapse text-left text-sm text-zinc-700 dark:text-zinc-200">
            <thead class="bg-zinc-50 text-xs uppercase tracking-[0.15em] text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                <tr>
                    <th class="px-6 py-4 font-semibold">NIP</th>
                    <th class="px-6 py-4 font-semibold">Nama (nmpeg)</th>
                    <th class="px-6 py-4 font-semibold">Pangkat</th>
                    <th class="px-6 py-4 font-semibold text-right">Total Pinjaman Aktif</th>
                    <th class="px-6 py-4 font-semibold text-center">Status</th>
                    <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($anggotas as $anggota)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">{{ $anggota->nip }}</td>
                        <td class="px-6 py-4">{{ $anggota->nmpeg }}</td>
                        <td class="px-6 py-4">
                            @if($anggota->pangkat)
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">
                                    {{ $anggota->pangkat }}
                                </span>
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @php
                                $totalPinjaman = $anggota->user ? $anggota->user->pinjaman->sum('jumlah_ajuan') : 0;
                            @endphp
                            @if($totalPinjaman > 0)
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($totalPinjaman, 0, ',', '.') }}</span>
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($anggota->is_active)
                                <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10 dark:bg-rose-400/10 dark:text-rose-400 dark:ring-rose-400/30">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:click="editAnggota({{ $anggota->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 shadow-sm transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <flux:icon name="pencil-square" class="size-3.5" />
                                <span>Edit</span>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <flux:icon name="users" class="size-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                            <p class="text-zinc-500 dark:text-zinc-400 text-base font-medium">Belum ada data anggota.</p>
                            <p class="text-zinc-400 dark:text-zinc-500 text-sm mt-1">Klik tombol "Sinkronkan Data API" di atas untuk mulai memuat data.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($anggotas->hasPages())
        <div class="mt-4">
            {{ $anggotas->links() }}
        </div>
    @endif

    <flux:modal name="modal-edit-anggota" class="md:w-[28rem]">
        <form wire:submit="saveAnggota" class="space-y-6">
            <div>
                <flux:heading size="lg">Edit Anggota</flux:heading>
                <flux:subheading>Ubah status anggota SIAPKLU ({{ $editingName }}).</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:switch wire:model="editingStatus" label="Status Aktif Anggota" description="Jika dinonaktifkan, anggota tetap ada di database namun berstatus nonaktif." />
            </div>

            <div class="flex gap-2 justify-end mt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Perubahan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
