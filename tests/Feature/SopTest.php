<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\SopRecord;
use App\Models\SopVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 9: SOP management. All SOP text here is TEST DATA.
 */
class SopTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $branchStaff;
    private User $commissaryStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('username', 'admin')->first();
        $this->branchStaff = $this->employee(Role::BRANCH_STAFF, Location::where('type', 'branch')->first());
        $this->commissaryStaff = $this->employee(Role::COMMISSARY_STAFF, Location::where('type', 'commissary')->first());
    }

    private function employee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }

    private function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->value('id');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sop_code' => 'sop-test-001',
            'title' => 'TEST Receiving a transfer',
            'category' => 'Inventory',
            'description' => 'TEST description',
            'content' => "## Steps\n\n1. TEST check each item\n2. TEST enter the received quantity",
            'effective_date' => null,
            'roles' => [$this->roleId(Role::BRANCH_STAFF)],
            'status' => 'active',
        ], $overrides);
    }

    private function createSop(array $overrides = []): SopRecord
    {
        $this->actingAs($this->admin)->post(route('sops.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return SopRecord::latest('id')->first();
    }

    // ---- Creating ----

    public function test_management_creates_an_sop_assigned_to_roles(): void
    {
        $sop = $this->createSop(['roles' => [$this->roleId(Role::BRANCH_STAFF), $this->roleId(Role::BRANCH_MANAGER)]]);

        $this->assertSame('SOP-TEST-001', $sop->sop_code); // saved in capitals
        $this->assertSame(1, $sop->version_no);
        $this->assertSame($this->admin->id, $sop->created_by);
        $this->assertCount(2, $sop->roles);  // decision Q4: one SOP, several roles
    }

    public function test_validation(): void
    {
        $this->createSop();

        $this->actingAs($this->admin)->post(route('sops.store'), $this->payload())->assertSessionHasErrors('sop_code'); // duplicate
        $this->actingAs($this->admin)->post(route('sops.store'), $this->payload(['sop_code' => 'X 1']))->assertSessionHasErrors('sop_code');
        $this->actingAs($this->admin)->post(route('sops.store'), $this->payload(['sop_code' => 'X-2', 'roles' => []]))->assertSessionHasErrors('roles');
        $this->actingAs($this->admin)->post(route('sops.store'), $this->payload(['sop_code' => 'X-3', 'content' => '']))->assertSessionHasErrors('content');
        $this->actingAs($this->admin)->post(route('sops.store'), $this->payload(['sop_code' => 'X-4', 'status' => 'archived']))->assertSessionHasErrors('status');
    }

    public function test_only_management_can_create_or_edit(): void
    {
        $this->actingAs($this->branchStaff)->post(route('sops.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->branchStaff)->get(route('sops.create'))->assertForbidden();

        $sop = $this->createSop();
        $this->actingAs($this->branchStaff)->get(route('sops.edit', $sop))->assertForbidden();
        $this->actingAs($this->branchStaff)->patch(route('sops.status', $sop), ['status' => 'archived'])->assertForbidden();
    }

    // ---- Who sees what (decision S4) ----

    public function test_staff_see_only_active_sops_for_their_role(): void
    {
        $mine = $this->createSop(['sop_code' => 'MINE', 'title' => 'TEST For branch staff']);
        $other = $this->createSop(['sop_code' => 'OTHER', 'title' => 'TEST For commissary', 'roles' => [$this->roleId(Role::COMMISSARY_STAFF)]]);
        $draft = $this->createSop(['sop_code' => 'DRAFT', 'title' => 'TEST Draft one', 'status' => 'draft']);

        $this->actingAs($this->branchStaff)->get(route('sops.index'))
            ->assertOk()
            ->assertSee('TEST For branch staff')
            ->assertDontSee('TEST For commissary')
            ->assertDontSee('TEST Draft one');

        $this->actingAs($this->branchStaff)->get(route('sops.show', $mine))->assertOk()->assertSee('TEST check each item');
        $this->actingAs($this->branchStaff)->get(route('sops.show', $other))->assertForbidden();
        $this->actingAs($this->branchStaff)->get(route('sops.show', $draft))->assertForbidden();

        // Management sees everything.
        $this->actingAs($this->admin)->get(route('sops.index'))
            ->assertSee('TEST For branch staff')->assertSee('TEST For commissary')->assertSee('TEST Draft one');
    }

    public function test_future_effective_date_hides_sop_from_staff_until_that_date(): void
    {
        $sop = $this->createSop(['effective_date' => today()->addDays(3)->toDateString()]);

        $this->actingAs($this->branchStaff)->get(route('sops.show', $sop))->assertForbidden();
        $this->actingAs($this->admin)->get(route('sops.index'))->assertSee('Scheduled');

        $this->travel(3)->days();
        $this->actingAs($this->branchStaff)->get(route('sops.show', $sop))->assertOk();
    }

    public function test_archived_sop_is_hidden_from_staff(): void
    {
        $sop = $this->createSop();

        $this->actingAs($this->admin)->patch(route('sops.status', $sop), ['status' => 'archived'])->assertSessionHas('success');

        $this->actingAs($this->branchStaff)->get(route('sops.show', $sop))->assertForbidden();
        $this->assertSame('archived', $sop->fresh()->status);
    }

    public function test_deactivated_role_assignment_hides_sops(): void
    {
        $sop = $this->createSop();
        $this->branchStaff->roles()->updateExistingPivot($this->roleId(Role::BRANCH_STAFF), ['is_active' => false]);

        $this->actingAs($this->branchStaff->fresh())->get(route('sops.show', $sop))->assertForbidden();
    }

    // ---- Version history (decision S2) ----

    public function test_status_cannot_be_changed_through_the_edit_form(): void
    {
        $sop = $this->createSop();

        // Status changes only through the status buttons, so the edit form rejects it.
        $this->actingAs($this->admin)->put(route('sops.update', $sop), $this->payload(['sop_code' => 'SOP-TEST-001', 'status' => 'draft']))
            ->assertSessionHasErrors('status');
    }

    public function test_editing_an_active_sop_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $sop = $this->createSop();
        $update = $this->payload(['sop_code' => 'SOP-TEST-001', 'content' => 'TEST version two wording']);
        unset($update['status']);

        $this->actingAs($this->admin)->put(route('sops.update', $sop), $update)->assertSessionHasNoErrors();

        $sop->refresh();
        $this->assertSame(2, $sop->version_no);
        $this->assertSame('TEST version two wording', $sop->content);

        $old = SopVersion::sole();
        $this->assertSame(1, $old->version_no);
        $this->assertStringContainsString('TEST check each item', $old->content);

        // Old versions are read-only and viewable by Management.
        $this->actingAs($this->admin)->get(route('sops.versions.show', [$sop, $old]))->assertOk()->assertSee('old version');
        $this->actingAs($this->branchStaff)->get(route('sops.versions.show', [$sop, $old]))->assertForbidden();

        $this->expectException(\LogicException::class);
        $old->update(['content' => 'tampered']);
    }

    public function test_editing_a_draft_or_only_roles_does_not_create_a_version(): void
    {
        $draft = $this->createSop(['status' => 'draft']);
        $update = $this->payload(['sop_code' => 'SOP-TEST-001', 'content' => 'TEST changed draft']);
        unset($update['status']);
        $this->actingAs($this->admin)->put(route('sops.update', $draft), $update);
        $this->assertSame(1, $draft->fresh()->version_no);

        $active = $this->createSop(['sop_code' => 'SOP-TEST-002']);
        $rolesOnly = $this->payload(['sop_code' => 'SOP-TEST-002', 'roles' => [$this->roleId(Role::BRANCH_MANAGER)]]);
        unset($rolesOnly['status']);
        $this->actingAs($this->admin)->put(route('sops.update', $active), $rolesOnly);
        $this->assertSame(1, $active->fresh()->version_no);

        $this->assertSame(0, SopVersion::count());
    }

    // ---- Formatting and safety (decision S1) ----

    public function test_markdown_is_rendered_and_html_is_stripped(): void
    {
        $sop = $this->createSop(['content' => "## TEST Heading\n\n**TEST bold**\n\n<script>alert('x')</script>\n\n[bad](javascript:alert(1))"]);

        $response = $this->actingAs($this->branchStaff)->get(route('sops.show', $sop));
        $response->assertOk()
            ->assertSee('<h2>TEST Heading</h2>', false)
            ->assertSee('<strong>TEST bold</strong>', false)
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('javascript:alert', false);
    }

    public function test_preview_endpoint_renders_safely_for_management_only(): void
    {
        $this->actingAs($this->admin)->postJson(route('sops.preview'), ['content' => "1. TEST step\n\n<b onclick=x>hi</b>"])
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => str_contains($html, '<li>TEST step</li>') && ! str_contains($html, 'onclick'));

        $this->actingAs($this->branchStaff)->postJson(route('sops.preview'), ['content' => 'x'])->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $sop = $this->createSop();

        $this->actingAs($this->admin)->get(route('sops.create'))->assertOk()->assertSee('Formatting help');
        $this->actingAs($this->admin)->get(route('sops.edit', $sop))->assertOk()->assertSee('creates version 2');
        $this->actingAs($this->branchStaff)->get(route('sops.print', $sop))->assertOk()->assertSee('Standard Operating Procedure');
        $this->actingAs($this->branchStaff)->get('/dashboard')->assertOk()->assertSee('SOPs Available to You')->assertSee('TEST Receiving a transfer');
        $this->actingAs($this->commissaryStaff)->get(route('sops.index'))->assertOk()->assertSee('No procedures are assigned to your role yet.');
    }
}
