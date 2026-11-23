<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 4A: inventory items and categories. All items here are TEST DATA.
 */
class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->first();
    }

    private function employee(string $roleSlug, string $locationType = Location::TYPE_BRANCH): User
    {
        $user = User::factory()->create([
            'location_id' => Location::where('type', $locationType)->value('id'),
        ]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'item_code' => 'test-001',
            'name' => 'TEST Rice Flour',
            'inventory_category_id' => InventoryCategory::first()->id,
            'item_type' => InventoryItem::TYPE_INGREDIENT,
            'unit_of_measure' => 'kg',
            'unit_cost' => '55.50',
            'reorder_level' => '5',
        ], $overrides);
    }

    private function makeItem(array $overrides = []): InventoryItem
    {
        $data = $this->payload($overrides);
        $data['item_code'] = strtoupper($data['item_code']);

        return InventoryItem::create($data);
    }

    // ---- Categories ----

    public function test_admin_can_manage_categories(): void
    {
        $this->actingAs($this->admin)
            ->post(route('inventory-categories.store'), ['name' => 'TEST Packaging'])
            ->assertRedirect(route('inventory-categories.index'));

        $category = InventoryCategory::where('name', 'TEST Packaging')->first();
        $this->assertNotNull($category);

        $this->actingAs($this->admin)
            ->put(route('inventory-categories.update', $category), ['name' => 'TEST Supplies'])
            ->assertSessionHasNoErrors();
        $this->assertSame('TEST Supplies', $category->fresh()->name);

        $this->actingAs($this->admin)->delete(route('inventory-categories.destroy', $category));
        $this->assertNull($category->fresh());
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $item = $this->makeItem();

        $this->actingAs($this->admin)
            ->delete(route('inventory-categories.destroy', $item->category))
            ->assertSessionHas('error');

        $this->assertNotNull($item->category->fresh());
    }

    // ---- Items: create / edit ----

    public function test_admin_can_add_item_and_code_is_saved_in_capitals(): void
    {
        $this->actingAs($this->admin)
            ->post(route('inventory-items.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $item = InventoryItem::where('item_code', 'TEST-001')->first();
        $this->assertNotNull($item);
        $this->assertSame('55.50', $item->unit_cost);
        $this->assertSame('active', $item->status);
    }

    public function test_item_code_must_be_unique_and_have_no_spaces(): void
    {
        $this->makeItem();

        $this->actingAs($this->admin)
            ->post(route('inventory-items.store'), $this->payload(['item_code' => 'TEST-001']))
            ->assertSessionHasErrors('item_code');

        $this->actingAs($this->admin)
            ->post(route('inventory-items.store'), $this->payload(['item_code' => 'HAS SPACE']))
            ->assertSessionHasErrors('item_code');
    }

    public function test_cost_and_reorder_level_cannot_be_negative(): void
    {
        $this->actingAs($this->admin)
            ->post(route('inventory-items.store'), $this->payload(['unit_cost' => '-1', 'reorder_level' => '-1']))
            ->assertSessionHasErrors(['unit_cost', 'reorder_level']);
    }

    public function test_editing_an_item_does_not_change_stock(): void
    {
        $item = $this->makeItem();
        $location = Location::first();
        InventoryStock::create(['location_id' => $location->id, 'inventory_item_id' => $item->id, 'quantity_on_hand' => 7]);

        $this->actingAs($this->admin)
            ->put(route('inventory-items.update', $item), $this->payload(['item_code' => 'TEST-001', 'name' => 'TEST Renamed', 'quantity_on_hand' => 999]))
            ->assertSessionHasNoErrors();

        $this->assertSame('TEST Renamed', $item->fresh()->name);
        $this->assertSame('7.000', InventoryStock::first()->quantity_on_hand);
    }

    public function test_admin_can_deactivate_and_reactivate_item(): void
    {
        $item = $this->makeItem();

        $this->actingAs($this->admin)->patch(route('inventory-items.status', $item), ['status' => 'inactive']);
        $this->assertSame('inactive', $item->fresh()->status);

        $this->actingAs($this->admin)->patch(route('inventory-items.status', $item), ['status' => 'active']);
        $this->assertSame('active', $item->fresh()->status);
    }

    // ---- Permissions ----

    public function test_staff_can_view_items_but_not_manage_them(): void
    {
        $staff = $this->employee(Role::BRANCH_STAFF);
        $item = $this->makeItem();

        $this->actingAs($staff)->get(route('inventory-items.index'))->assertOk()->assertSee('TEST Rice Flour');
        $this->actingAs($staff)->get(route('inventory-items.show', $item))->assertOk();

        $this->actingAs($staff)->get(route('inventory-items.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('inventory-items.store'), $this->payload(['item_code' => 'X-1']))->assertForbidden();
        $this->actingAs($staff)->get(route('inventory-items.edit', $item))->assertForbidden();
        $this->actingAs($staff)->get(route('inventory-categories.index'))->assertForbidden();
    }

    public function test_unit_cost_is_hidden_from_non_management(): void
    {
        $this->makeItem(['unit_cost' => '123.45']);
        $staff = $this->employee(Role::BRANCH_MANAGER);

        $this->actingAs($this->admin)->get(route('inventory-items.index'))->assertSee('123.45');
        $this->actingAs($staff)->get(route('inventory-items.index'))->assertDontSee('123.45');
    }

    public function test_staff_do_not_see_inactive_items_by_default(): void
    {
        $this->makeItem(['name' => 'TEST Old Item', 'item_code' => 'OLD-1'])->update(['status' => 'inactive']);
        $staff = $this->employee(Role::BRANCH_STAFF);

        $this->actingAs($staff)->get(route('inventory-items.index'))->assertDontSee('TEST Old Item');
        $this->actingAs($this->admin)->get(route('inventory-items.index'))->assertSee('TEST Old Item');
    }

    public function test_search_finds_items_by_name_or_code(): void
    {
        $this->makeItem();
        $this->makeItem(['item_code' => 'BEV-001', 'name' => 'TEST Calamansi Juice']);

        $this->actingAs($this->admin)->get(route('inventory-items.index', ['search' => 'calamansi']))
            ->assertSee('TEST Calamansi Juice')->assertDontSee('TEST Rice Flour');

        $this->actingAs($this->admin)->get(route('inventory-items.index', ['search' => 'TEST-001']))
            ->assertSee('TEST Rice Flour')->assertDontSee('TEST Calamansi Juice');
    }

    // ---- Item detail: location scoping ----

    public function test_item_detail_shows_only_the_users_own_location(): void
    {
        $item = $this->makeItem();
        $commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();
        $staff = $this->employee(Role::BRANCH_STAFF);
        $ownBranch = $staff->location;

        foreach ([$commissary, $ownBranch] as $location) {
            InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'user_id' => $this->admin->id,
                'transaction_type' => InventoryTransaction::RECEIVED,
                'quantity' => 1,
                'balance_after' => 1,
                'transaction_date' => now(),
                'remarks' => "TEST at {$location->name}",
            ]);
        }

        $this->actingAs($staff)->get(route('inventory-items.show', $item))
            ->assertSee("TEST at {$ownBranch->name}")
            ->assertDontSee("TEST at {$commissary->name}");

        $this->actingAs($this->admin)->get(route('inventory-items.show', $item))
            ->assertSee("TEST at {$ownBranch->name}")
            ->assertSee("TEST at {$commissary->name}");
    }
}
