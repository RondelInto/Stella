<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the FIRST Management (Admin) account so someone can log in and create other employees.
 *
 * The username and password come from .env (never hardcoded in code):
 *   ADMIN_USERNAME=admin
 *   ADMIN_PASSWORD=your-strong-password
 * If ADMIN_PASSWORD is empty, a random password is generated and printed once in the terminal.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('ADMIN_USERNAME', 'admin');

        if (User::where('username', $username)->exists()) {
            $this->command?->info("Admin account '{$username}' already exists. Skipped.");

            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::password(16);

        $admin = User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'username' => $username,
            'email' => env('ADMIN_EMAIL') ?: null,
            'password' => $password, // hashed automatically by the User model
            'account_status' => 'active',
            'location_id' => null,   // Management oversees all locations
        ]);

        $admin->roles()->attach(Role::where('slug', Role::ADMIN)->value('id'), [
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $this->command?->warn("Admin account created. Username: {$username}");

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Generated password (shown ONCE, save it now): {$password}");
        }
    }
}
