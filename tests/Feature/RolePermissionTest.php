<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 3: role-based access control.
 * All users here are TEST DATA in the testing database only.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // A fake protected page, to test that the 'can:' middleware blocks access on the server.
        Route::middleware(['web', 'auth', 'can:manage-users'])
            ->get('/_test/manage-users', fn () => 'OK');
    }

    private function userWithRole(?string $roleSlug, ?Location $location = null): User
    {
        $user = User::factory()->create(['location_id' => $location?->id]);

        if ($roleSlug) {
            $user->roles()->attach(Role::where('slug', $roleSlug)->value('id'), ['is_active' => true]);
        }

        return $user->fresh();
    }

    private function branch(): Location
    {
        return Location::where('type', Location::TYPE_BRANCH)->first();
    }

    /**
     * [role, permission, expected]
     */
    public static function permissionMatrix(): array
    {
        return [
            'admin manages users' => [Role::ADMIN, 'manage-users', true],
            'admin imports POS sales' => [Role::ADMIN, 'import-pos-sales', true],
            'admin manages SOPs' => [Role::ADMIN, 'manage-sops', true],
            'admin cannot record production' => [Role::ADMIN, 'record-production', false],

            'branch manager receives transfers' => [Role::BRANCH_MANAGER, 'receive-stock-transfers', true],
            'branch manager views branch reports' => [Role::BRANCH_MANAGER, 'view-branch-reports', true],
            'branch manager cannot manage users' => [Role::BRANCH_MANAGER, 'manage-users', false],
            'branch manager cannot import POS' => [Role::BRANCH_MANAGER, 'import-pos-sales', false],

            'commissary prepares transfers' => [Role::COMMISSARY_STAFF, 'prepare-stock-transfers', true],
            'commissary records production' => [Role::COMMISSARY_STAFF, 'record-production', true],
            'commissary cannot view sales analysis' => [Role::COMMISSARY_STAFF, 'view-sales-analysis', false],

            'branch staff views SOPs' => [Role::BRANCH_STAFF, 'view-sops', true],
            'branch staff views inventory' => [Role::BRANCH_STAFF, 'view-inventory', true],
            'branch staff cannot record production' => [Role::BRANCH_STAFF, 'record-production', false],
            'branch staff cannot view reports' => [Role::BRANCH_STAFF, 'view-branch-reports', false],
            'branch staff cannot manage SOPs' => [Role::BRANCH_STAFF, 'manage-sops', false],
        ];
    }

    #[DataProvider('permissionMatrix')]
    public function test_role_permissions(string $role, string $permission, bool $expected): void
    {
        $user = $this->userWithRole($role, $role === Role::ADMIN ? null : $this->branch());

        $this->assertSame($expected, $user->can($permission));
    }

    public function test_user_without_role_has_no_permissions(): void
    {
        $user = $this->userWithRole(null);

        foreach (array_keys(config('permissions')) as $permission) {
            $this->assertFalse($user->can($permission), "User without role should not have '{$permission}'.");
        }
    }

    public function test_deactivated_role_gives_no_permission(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->value('id'), ['is_active' => false]);

        $this->assertFalse($user->fresh()->can('manage-users'));
    }

    public function test_server_blocks_unauthorized_role_with_403(): void
    {
        $staff = $this->userWithRole(Role::BRANCH_STAFF, $this->branch());

        $this->actingAs($staff)->get('/_test/manage-users')->assertForbidden();
    }

    public function test_server_allows_authorized_role(): void
    {
        $admin = User::where('username', 'admin')->first();

        $this->actingAs($admin)->get('/_test/manage-users')->assertOk();
    }

    public function test_branch_users_can_only_access_their_own_location(): void
    {
        [$ownBranch, $otherBranch] = Location::where('type', Location::TYPE_BRANCH)->take(2)->get();
        $manager = $this->userWithRole(Role::BRANCH_MANAGER, $ownBranch);

        $this->assertTrue($manager->canAccessLocation($ownBranch));
        $this->assertFalse($manager->canAccessLocation($otherBranch));
        $this->assertSame([$ownBranch->id], $manager->accessibleLocationIds());
    }

    public function test_management_can_access_all_locations(): void
    {
        $admin = User::where('username', 'admin')->first();

        foreach (Location::all() as $location) {
            $this->assertTrue($admin->canAccessLocation($location));
        }
        $this->assertCount(4, $admin->accessibleLocationIds());
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = $this->userWithRole(Role::BRANCH_STAFF, $this->branch());

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['account_status' => 'inactive']);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_access_denied_page_is_friendly(): void
    {
        $staff = $this->userWithRole(Role::BRANCH_STAFF, $this->branch());

        $this->actingAs($staff)->get('/_test/manage-users')
            ->assertForbidden()
            ->assertSee('You do not have permission to access this page.');
    }
}
