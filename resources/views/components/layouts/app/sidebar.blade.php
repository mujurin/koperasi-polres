<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
            <x-app-logo class="size-8" href="#"></x-app-logo>
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.group heading="Platform" class="grid">
                <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>Dashboard</flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Simpanan Koperasi" class="grid mt-4">
                <flux:navlist.item icon="users" :href="route('simpanan.index')"
                    :current="request()->routeIs('simpanan.index')" wire:navigate>
                    Simpanan
                </flux:navlist.item>
                <flux:navlist.item icon="arrow-down-tray" :href="route('simpanan.penarikan')"
                    :current="request()->routeIs('simpanan.penarikan')" wire:navigate>
                    Persetujuan Penarikan
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Pinjaman Koperasi" class="grid mt-4">
                <flux:navlist.item icon="clock" href="{{ route('pinjaman.antrian') }}"
                    :current="request()->routeIs('pinjaman.antrian') || request()->routeIs('pinjaman.review')"
                    wire:navigate>
                    Antrian Pinjaman
                </flux:navlist.item>
                <flux:navlist.item icon="document-text" href="{{ route('pinjaman.index') }}"
                    :current="request()->routeIs('pinjaman.index')" wire:navigate>
                    Daftar Pinjaman
                </flux:navlist.item>
                <flux:navlist.item icon="arrow-down-on-square" href="{{ route('pinjaman.tarik-setoran') }}"
                    :current="request()->routeIs('pinjaman.tarik-setoran')" wire:navigate>
                    Tarik Setoran
                </flux:navlist.item>
                <flux:navlist.item icon="chart-bar" href="{{ route('pinjaman.rekap') }}"
                    :current="request()->routeIs('pinjaman.rekap')" wire:navigate>
                    Rekap Pinjaman
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Laporan & Akuntansi" class="grid mt-4">
                <flux:navlist.item icon="scale" href="{{ route('laporan.neraca-saldo') }}"
                    :current="request()->routeIs('laporan.neraca-saldo')" wire:navigate>
                    Neraca Saldo
                </flux:navlist.item>
                <flux:navlist.item icon="document-chart-bar" href="{{ route('laporan.laba-rugi') }}"
                    :current="request()->routeIs('laporan.laba-rugi')" wire:navigate>
                    Laba Rugi (PHU)
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Alat Admin" class="grid mt-4">
                <flux:navlist.item icon="wrench-screwdriver" href="{{ route('admin.dummy-angsuran') }}"
                    :current="request()->routeIs('admin.dummy-angsuran')" wire:navigate>
                    Dummy Angsuran
                </flux:navlist.item>
                <flux:navlist.item icon="trash" href="{{ route('admin.reset-data') }}"
                    :current="request()->routeIs('admin.reset-data')" wire:navigate>
                    Reset Data
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />
        <!-- Desktop User Menu -->
        <flux:dropdown position="bottom" align="start">
            <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()"
                icon-trailing="chevrons-up-down" />

            <flux:menu class="w-[220px]">
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>

</html>