<?php

namespace Database\Seeders;

use App\Models\NolbaseAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NolbaseAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed in dev. In production, run `php artisan nolbase:admin:create`
        // (TODO once that command exists) or insert manually via tinker.
        if (! app()->environment('local')) {
            return;
        }

        NolbaseAdmin::firstOrCreate(
            ['email' => 'admin@nolbase.test'],
            [
                'name' => 'Dev Superadmin',
                'password' => Hash::make('password'),
                'role' => NolbaseAdmin::ROLE_SUPERADMIN,
            ],
        );
    }
}
