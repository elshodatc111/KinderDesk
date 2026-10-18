<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('kinderdesk.superadmin');
        $phone = normalize_phone($cfg['phone']) ?? $cfg['phone'];

        User::firstOrCreate(['phone' => $phone], [
            'name' => $cfg['name'],
            'password' => $cfg['password'],
            'is_super_admin' => true,
            'status' => 'active',
            'phone_verified_at' => now(),
            'locale' => 'ru',
        ]);
    }
}
