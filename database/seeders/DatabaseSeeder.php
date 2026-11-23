<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the REQUIRED starting data.
     * Run with: php artisan db:seed
     * Order matters: roles must exist before the admin account is given a role.
     */
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            RoleSeeder::class,
            InventoryCategorySeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
