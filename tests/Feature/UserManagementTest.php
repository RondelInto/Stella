<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 2: User and Role Management. All accounts here are TEST DATA.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->first();
    }

    private function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->value('id');
    }

    private function branch(): Location
    {
        return Location::where('type', Location::TYPE_BRANCH)->first();
    }

    private function commissary(): Location
    {
        return Location::where('type', Location::TYPE_COMMISSARY)->first();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'username' => 'test.employee',
            'email' => '',
            'contact_no' => '',
            'roles' => [$this->roleId(Role::BRANCH_STAFF)],
            'location_id' => $this->branch()->id,
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ], $overrides);
    }

    private function makeEmployee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([$this->roleId($roleSlug)]);

        return $user->fresh();
    }

    // ---- Access ----

    public function test_admin_can_view_employee_and_role_pages(): void
    {
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->assertSee('admin');
        $this->actingAs($this->admin)->get(route('users.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('roles.index'))->assertOk()->assertSee('Commissary Staff');
    }

    public function test_non_admin_roles_are_blocked_from_user_management(): void
    {
        $manager = $this->makeEmployee(Role::BRANCH_MANAGER, $this->branch());

        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('users.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($manager)->get(route('roles.index'))->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'test.employee']);
    }

    // ---- Create ----

    public function test_admin_can_create_employee_who_can_then_log_in(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload())
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $user = User::where('username', 'test.employee')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email);
        $this->assertTrue(Hash::check('Secret123!', $user->password));
        $this->assertTrue($user->hasRole(Role::BRANCH_STAFF));
        $this->assertSame($this->branch()->id, $user->location_id);

        auth()->logout();
        $this->post('/login', ['username' => 'test.employee', 'password' => 'Secret123!']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_must_be_unique_and_valid(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload(['username' => 'admin']))
            ->assertSessionHasErrors('username');

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload(['username' => 'has space']))
            ->assertSessionHasErrors('username');
    }

    public function test_branch_roles_require_a_branch_location(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload(['location_id' => $this->commissary()->id]))
            ->assertSessionHasErrors('location_id');

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload(['location_id' => '']))
            ->assertSessionHasErrors('location_id');
    }

    public function test_commissary_staff_require_the_commissary(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload([
                'roles' => [$this->roleId(Role::COMMISSARY_STAFF)],
                'location_id' => $this->branch()->id,
            ]))
            ->assertSessionHasErrors('location_id');
    }

    public function test_admin_only_account_has_no_location(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload([
                'username' => 'manager2',
                'roles' => [$this->roleId(Role::ADMIN)],
                'location_id' => $this->branch()->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(User::where('username', 'manager2')->value('location_id'));
    }

    public function test_at_least_one_role_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->validPayload(['roles' => []]))
            ->assertSessionHasErrors('roles');
    }

    // ---- Update and role history ----

    public function test_changing_roles_keeps_history_instead_of_deleting(): void
    {
        $user = $this->makeEmployee(Role::BRANCH_STAFF, $this->branch());

        $this->actingAs($this->admin)->put(route('users.update', $user), $this->validPayload([
            'username' => $user->username,
            'roles' => [$this->roleId(Role::BRANCH_MANAGER)],
        ]))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->hasRole(Role::BRANCH_MANAGER));
        $this->assertFalse($user->hasRole(Role::BRANCH_STAFF));
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $this->roleId(Role::BRANCH_STAFF),
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), $this->validPayload([
            'username' => 'admin',
            'roles' => [$this->roleId(Role::BRANCH_STAFF)],
        ]))->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->isManagement());
    }

    // ---- Status and password ----

    public function test_admin_can_deactivate_and_reactivate_an_employee(): void
    {
        $user = $this->makeEmployee(Role::BRANCH_STAFF, $this->branch());

        $this->actingAs($this->admin)
            ->patch(route('users.status', $user), ['account_status' => 'inactive'])
            ->assertSessionHas('success');
        $this->assertSame('inactive', $user->fresh()->account_status);

        $this->actingAs($this->admin)
            ->patch(route('users.status', $user), ['account_status' => 'active']);
        $this->assertSame('active', $user->fresh()->account_status);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('users.status', $this->admin), ['account_status' => 'inactive'])
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->isActive());
    }

    public function test_one_admin_can_deactivate_another_admin(): void
    {
        $secondAdmin = User::factory()->create();
        $secondAdmin->syncRoleAssignments([$this->roleId(Role::ADMIN)]);

        $this->actingAs($secondAdmin)
            ->patch(route('users.status', $this->admin), ['account_status' => 'inactive'])
            ->assertSessionHas('success');

        $this->assertFalse($this->admin->fresh()->isActive());
    }

    public function test_admin_can_reset_an_employee_password(): void
    {
        $user = $this->makeEmployee(Role::BRANCH_STAFF, $this->branch());

        $this->actingAs($this->admin)->put(route('users.password', $user), [
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewPass123!', $user->fresh()->password));
    }

    // ---- Roles ----

    public function test_admin_can_edit_role_details_but_not_slug(): void
    {
        $role = Role::where('slug', Role::BRANCH_STAFF)->first();

        $this->actingAs($this->admin)->put(route('roles.update', $role), [
            'name' => 'Branch Crew',
            'description' => 'Updated description',
            'responsibilities' => 'Line 1',
            'slug' => 'hacked',
        ])->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('Branch Crew', $role->name);
        $this->assertSame(Role::BRANCH_STAFF, $role->slug);
    }

    public function test_dashboard_shows_employee_counts_for_admin_only(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Active Employees');

        $staff = $this->makeEmployee(Role::BRANCH_STAFF, $this->branch());
        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertDontSee('Active Employees');
    }
}
