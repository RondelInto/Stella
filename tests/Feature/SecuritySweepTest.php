<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 10 of the System Test Guide, automated: every role types every protected address.
 * If this table changes, update the guide's Part 10 table too.
 */
class SecuritySweepTest extends TestCase
{
    use RefreshDatabase;

    /** address => [admin, commissary staff, branch manager, branch staff] (true = opens, false = 403) */
    private const MATRIX = [
        'users/create' => [true, false, false, false],
        'locations/create' => [true, false, false, false],
        'inventory/items/create' => [true, false, false, false],
        'inventory/counts/create' => [false, true, true, true],
        'inventory/transactions/create' => [true, true, true, true],
        'transfers/create' => [false, true, false, false],
        'production/create' => [false, true, false, false],
        'production/planning' => [true, true, true, false],
        'sops/create' => [true, false, false, false],
        'sales-imports/create' => [true, false, false, false],
        'analysis' => [true, false, false, false],
        'reports' => [true, false, true, false],
    ];

    public function test_every_role_reaches_only_its_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();
        $branch = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->first();

        $users = [
            User::where('username', 'admin')->first(),
            $this->employee(Role::COMMISSARY_STAFF, $commissary),
            $this->employee(Role::BRANCH_MANAGER, $branch),
            $this->employee(Role::BRANCH_STAFF, $branch),
        ];

        $wrong = [];
        foreach (self::MATRIX as $address => $expected) {
            foreach ($users as $i => $user) {
                $status = $this->actingAs($user)->get('/'.$address)->getStatusCode();
                $opens = $status === 200;
                if ($opens !== $expected[$i] || (! $opens && $status !== 403)) {
                    $wrong[] = "{$address} as {$user->username}: got {$status}";
                }
            }
        }

        $this->assertSame([], $wrong);
    }

    public function test_logged_out_users_go_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        $this->seed(DatabaseSeeder::class);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['username' => 'admin', 'password' => 'wrong-password']);
        }

        $this->post(route('login'), ['username' => 'admin', 'password' => 'wrong-password'])
            ->assertSessionHasErrors();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first());
    }

    private function employee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }
}
