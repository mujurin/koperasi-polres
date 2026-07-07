<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nrp',
        'email',
        'password',
    ];

    /**
     * Override the username used for authentication.
     */
    public function getAuthIdentifierName(): string
    {
        return 'nrp';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn(string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function simpananPokok()
    {
        return $this->hasOne(SimpananPokok::class);
    }

    public function simpananWajib()
    {
        return $this->hasMany(SimpananWajib::class);
    }

    public function penarikan()
    {
        return $this->hasMany(Penarikan::class);
    }

    public function pinjaman()
    {
        return $this->hasMany(Pinjaman::class);
    }

    public function totalSimpanan(): float
    {
        $pokok = $this->simpananPokok?->jumlah ?? 0;
        $wajib = $this->simpananWajib()->sum('jumlah');
        return (float) ($pokok + $wajib);
    }

    public function totalPenarikan(): float
    {
        return (float) $this->penarikan()->where('status', 'disetujui')->sum('jumlah');
    }

    public function saldoAkhir(): float
    {
        return $this->totalSimpanan() - $this->totalPenarikan();
    }

    public function isAdmin(): bool
    {
        return $this->nrp === '135410267';
    }
}
