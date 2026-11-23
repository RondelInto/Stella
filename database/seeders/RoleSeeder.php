<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The four system roles from the project documentation (Use Case Diagram / Security Plan).
 * Responsibilities can be edited later by Management.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'slug' => Role::ADMIN,
                'name' => 'Management (Admin)',
                'description' => 'Oversees the system and business operations across all locations.',
                'responsibilities' => 'Manage employee accounts and roles; manage inventory records; import POS sales data; view sales analysis and production forecasts; manage SOPs; access centralized reports.',
            ],
            [
                'slug' => Role::BRANCH_MANAGER,
                'name' => 'Branch Manager',
                'description' => 'Monitors branch operations.',
                'responsibilities' => 'View branch inventory; record authorized inventory transactions; monitor stock transfers; confirm stock receipts; view inventory and production records; access relevant SOPs; view branch reports.',
            ],
            [
                'slug' => Role::COMMISSARY_STAFF,
                'name' => 'Commissary Staff',
                'description' => 'Handles food production and inventory at the central commissary.',
                'responsibilities' => 'View commissary inventory; record inventory transactions; prepare and record stock transfers; record food production; monitor remaining stocks; view production records; access relevant SOPs.',
            ],
            [
                'slug' => Role::BRANCH_STAFF,
                'name' => 'Branch Staff',
                'description' => 'Performs assigned branch-level operational tasks.',
                'responsibilities' => 'View branch inventory; record authorized stock transactions; confirm stock receipts where permitted; view relevant inventory records; access assigned SOPs.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
