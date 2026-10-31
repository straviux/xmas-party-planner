<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates ONE host/admin user. firstOrCreate means re-seeding does
     * NOT reset a password the host changed via Change password.
     * Username/password come from ADMIN_USERNAME / ADMIN_PASSWORD in
     * .env (defaults admin / admin1234) — change them immediately
     * after first sign-in via the host dashboard Change password.
     * Email is never used for login; it only fills the users table.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'name' => 'Admin',
                'email' => 'admin@localhost',
                'password' => env('ADMIN_PASSWORD', 'admin1234'),
            ],
        );
    }
}
