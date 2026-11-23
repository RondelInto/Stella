<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

/**
 * REAL categories mentioned in Chapter 1 (Inventory and Stock Monitoring).
 * Management can add more categories later through the system.
 */
class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Beverages', 'Cooked Food', 'Kakanin'] as $name) {
            InventoryCategory::firstOrCreate(['name' => $name]);
        }
    }
}
