<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['nrp' => '135410267'],
            [
                'name' => 'Admin',
                'nrp' => '135410267',
                'email' => 'admin@koperasipolres.local',
                'password' => Hash::make('135410267'),
            ]
        );
    }
}
