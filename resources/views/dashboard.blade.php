<x-layouts.app>
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Dashboard Admin</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Selamat datang di panel administrasi Koperasi Polres.
            </p>
        </div>

        <livewire:admin.sync-anggota />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-2">
            <div
                class="rounded-2xl bg-white border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 p-6 flex flex-col items-center justify-center text-center shadow-sm">
                <div
                    class="w-12 h-12 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center mb-3">
                    <flux:icon name="users" class="size-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <h3 class="text-3xl font-bold text-zinc-900 dark:text-white">{{ \App\Models\User::count() }}</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">Total Anggota</p>
            </div>

            <div
                class="rounded-2xl bg-white border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 p-6 flex flex-col items-center justify-center text-center shadow-sm">
                <div
                    class="w-12 h-12 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center mb-3">
                    <flux:icon name="banknotes" class="size-6 text-emerald-600 dark:text-emerald-400" />
                </div>
                <h3 class="text-3xl font-bold text-zinc-900 dark:text-white">
                    Rp {{ number_format(\App\Models\SimpananWajib::sum('jumlah'), 0, ',', '.') }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">Total Simpanan Wajib</p>
            </div>

            <div
                class="rounded-2xl bg-white border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 p-6 flex flex-col items-center justify-center text-center shadow-sm">
                <div
                    class="w-12 h-12 bg-orange-50 dark:bg-orange-900/30 rounded-xl flex items-center justify-center mb-3">
                    <flux:icon name="clock" class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
                <h3 class="text-3xl font-bold text-zinc-900 dark:text-white">
                    {{ \App\Models\Pinjaman::where('status', 'proses')->count() }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">Antrian Pinjaman</p>
            </div>
        </div>
    </div>
</x-layouts.app>