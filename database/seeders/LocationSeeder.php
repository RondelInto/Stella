<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

/**
 * REAL company data (from Chapter 1): the three branches and the central commissary.
 * Addresses and contact numbers are left blank; Management can fill them in later.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'Central Commissary', 'type' => Location::TYPE_COMMISSARY],
            ['name' => 'Matina-Pangi Branch', 'type' => Location::TYPE_BRANCH],
            ['name' => 'Catalunan Pequeño Branch', 'type' => Location::TYPE_BRANCH],
            ['name' => 'Puan Branch', 'type' => Location::TYPE_BRANCH],
        ];

        foreach ($locations as $location) {
            // updateOrCreate makes the seeder safe to run more than once (no duplicates).
            Location::updateOrCreate(['name' => $location['name']], $location + ['status' => 'active']);
        }
    }
}
