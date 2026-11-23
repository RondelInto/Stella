<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 3: Location Management. New locations created here are TEST DATA.
 */
class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->first();
    }

    private function branch(): Location
    {
        return Location::where('type', Location::TYPE_BRANCH)->first();
    }

    private function staffAt(Location $location, string $status = 'active'): User
    {
        $user = User::factory()->create(['location_id' => $location->id, 'account_status' => $status]);
        $user->syncRoleAssignments([Role::where('slug', Role::BRANCH_STAFF)->value('id')]);

        return $user;
    }

    public function test_admin_sees_all_locations(): void
    {
        $this->actingAs($this->admin)->get(route('locations.index'))
            ->assertOk()
            ->assertSee('Central Commissary')
            ->assertSee('Matina-Pangi Branch')
            ->assertSee('Catalunan Pequeño Branch')
            ->assertSee('Puan Branch');
    }

    public function test_other_roles_cannot_manage_locations(): void
    {
        $staff = $this->staffAt($this->branch());

        $this->actingAs($staff)->get(route('locations.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('locations.store'), ['name' => 'TEST Branch'])->assertForbidden();
    }

    public function test_new_locations_are_always_branches(): void
    {
        $this->actingAs($this->admin)->post(route('locations.store'), [
            'name' => 'TEST Branch',
            'type' => 'commissary', // ignored
        ])->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('locations', ['name' => 'TEST Branch', 'type' => 'branch', 'status' => 'active']);
        $this->assertSame(1, Location::where('type', Location::TYPE_COMMISSARY)->count());
    }

    public function test_location_names_must_be_unique(): void
    {
        $this->actingAs($this->admin)
            ->post(route('locations.store'), ['name' => 'Puan Branch'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_details_but_not_type(): void
    {
        $branch = $this->branch();

        $this->actingAs($this->admin)->put(route('locations.update', $branch), [
            'name' => $branch->name,
            'address' => 'TEST ADDRESS',
            'contact_no' => '000',
            'type' => 'commissary', // ignored
        ])->assertSessionHasNoErrors();

        $branch->refresh();
        $this->assertSame('TEST ADDRESS', $branch->address);
        $this->assertSame(Location::TYPE_BRANCH, $branch->type);
    }

    public function test_branch_with_active_employees_cannot_be_deactivated(): void
    {
        $branch = $this->branch();
        $this->staffAt($branch);

        $this->actingAs($this->admin)
            ->patch(route('locations.status', $branch), ['status' => 'inactive'])
            ->assertSessionHas('error');

        $this->assertSame('active', $branch->fresh()->status);
    }

    public function test_empty_branch_can_be_deactivated_and_reactivated(): void
    {
        $branch = $this->branch();
        $this->staffAt($branch, 'inactive'); // inactive employees don't block

        $this->actingAs($this->admin)
            ->patch(route('locations.status', $branch), ['status' => 'inactive'])
            ->assertSessionHas('success');
        $this->assertSame('inactive', $branch->fresh()->status);

        $this->actingAs($this->admin)
            ->patch(route('locations.status', $branch), ['status' => 'active']);
        $this->assertSame('active', $branch->fresh()->status);
    }

    public function test_commissary_cannot_be_deactivated(): void
    {
        $commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();

        $this->actingAs($this->admin)
            ->patch(route('locations.status', $commissary), ['status' => 'inactive'])
            ->assertSessionHas('error');

        $this->assertSame('active', $commissary->fresh()->status);
    }

    public function test_employees_cannot_be_assigned_to_an_inactive_branch(): void
    {
        $branch = $this->branch();
        $branch->update(['status' => 'inactive']);

        $this->actingAs($this->admin)->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'username' => 'test.employee',
            'roles' => [Role::where('slug', Role::BRANCH_STAFF)->value('id')],
            'location_id' => $branch->id,
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertSessionHasErrors('location_id');
    }

    public function test_existing_employee_at_inactive_branch_can_still_be_edited(): void
    {
        $branch = $this->branch();
        $user = $this->staffAt($branch, 'inactive');
        $branch->update(['status' => 'inactive']);

        $this->actingAs($this->admin)->put(route('users.update', $user), [
            'first_name' => 'Renamed',
            'last_name' => $user->last_name,
            'username' => $user->username,
            'roles' => [Role::where('slug', Role::BRANCH_STAFF)->value('id')],
            'location_id' => $branch->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $user->fresh()->first_name);
        $this->assertSame($branch->id, $user->fresh()->location_id);
    }
}
