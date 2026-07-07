<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string')]
    public string $nrp = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        // 1. Coba login lokal dulu (untuk admin & user yang sudah ada di DB)
        if (Auth::attempt(['nrp' => $this->nrp, 'password' => $this->password], $this->remember)) {
            RateLimiter::clear($this->throttleKey());
            Session::regenerate();

            $default = Auth::user()->isAdmin() ? route('dashboard', absolute: false) : route('anggota.dashboard', absolute: false);

            $intended = session()->pull('url.intended', $default);
            if (!Auth::user()->isAdmin() && !Str::contains($intended, 'anggota')) {
                $intended = $default;
            }
            $this->redirect($intended, navigate: true);
            return;
        }

        // 2. Jika login lokal gagal, coba API eksternal siapklu.com
        try {
            $response = Http::timeout(10)->post('https://siapklu.com/api/login', [
                'nrp' => $this->nrp,
                'password' => $this->password,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && $data['status'] === true) {
                // API berhasil – buat/update user lokal dari data API
                $apiUser = $data['data'];

                $user = User::updateOrCreate(
                    ['nrp' => $apiUser['nip']],
                    [
                        'name' => $apiUser['nmpeg'] ?? $apiUser['nip'],
                        'nrp' => $apiUser['nip'],
                        'email' => $apiUser['nip'] . '@koperasipolres.local',
                        'password' => Hash::make($this->password),
                    ]
                );

                Auth::login($user, $this->remember);
                RateLimiter::clear($this->throttleKey());
                Session::regenerate();

                $default = $user->isAdmin() ? route('dashboard', absolute: false) : route('anggota.dashboard', absolute: false);

                $intended = session()->pull('url.intended', $default);
                if (!$user->isAdmin() && !Str::contains($intended, 'anggota')) {
                    $intended = $default;
                }
                $this->redirect($intended, navigate: true);
                return;
            }
        } catch (\Exception $e) {
            // Jika API tidak bisa dihubungi, lanjut ke error login biasa
        }

        // 3. Kedua cara gagal
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'nrp' => __('auth.failed'),
        ]);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'nrp' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->nrp) . '|' . request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="PRIMKOPPOL LOTARA" description="Masukkan NRP dan password untuk masuk" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <!-- NRP -->
        <flux:input wire:model="nrp" label="{{ __('NRP') }}" type="text" name="nrp" required autofocus
            autocomplete="username" placeholder="Masukkan NRP Anda" />

        <!-- Password -->
        <div class="relative">
            <flux:input wire:model="password" label="{{ __('Password') }}" type="password" name="password" required
                autocomplete="current-password" placeholder="Password" />
        </div>

        <!-- Remember Me -->
        <flux:checkbox wire:model="remember" label="{{ __('Ingat Saya') }}" />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Masuk') }}</flux:button>
        </div>
    </form>
</div>