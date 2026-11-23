<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inventory item list.
 *  - View (list + detail): anyone with 'view-inventory'
 *  - Add/edit/deactivate: Management only ('manage-inventory-items')
 * Unit costs are only shown to users who can manage items.
 */
class InventoryItemController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'category', 'type', 'status']);
        $canManage = $request->user()->can('manage-inventory-items');

        // Non-managers only see active items unless they filter otherwise.
        $status = $filters['status'] ?? ($canManage ? null : 'active');

        $items = InventoryItem::query()
            ->with('category')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%")))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('inventory_category_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('item_type', $type))
            ->when($status, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.items.index', [
            'items' => $items,
            'filters' => $filters,
            'categories' => InventoryCategory::orderBy('name')->get(),
            'types' => self::typeLabels(),
            'canManage' => $canManage,
        ]);
    }

    public function create(): View
    {
        return view('inventory.items.create', $this->formOptions());
    }

    public function store(InventoryItemRequest $request): RedirectResponse
    {
        $item = InventoryItem::create($request->validated() + ['status' => 'active']);

        return redirect()->route('inventory-items.show', $item)
            ->with('success', "Item '{$item->name}' ({$item->item_code}) was added.");
    }

    /**
     * Item detail: stock at each location the user may see + movement history (traceability).
     */
    public function show(Request $request, InventoryItem $item): View
    {
        $user = $request->user();
        $locationIds = $user->accessibleLocationIds();

        $locations = Location::whereIn('id', $locationIds)
            ->orderByRaw("type = 'commissary' desc")
            ->orderBy('name')
            ->get();

        $stocks = $item->stocks()->whereIn('location_id', $locationIds)->get()->keyBy('location_id');

        $transactions = $item->transactions()
            ->with(['location', 'user'])
            ->whereIn('location_id', $locationIds)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(20);

        return view('inventory.items.show', [
            'item' => $item->load('category'),
            'locations' => $locations,
            'stocks' => $stocks,
            'transactions' => $transactions,
            'types' => self::typeLabels(),
            'canManage' => $user->can('manage-inventory-items'),
        ]);
    }

    public function edit(InventoryItem $item): View
    {
        return view('inventory.items.edit', ['item' => $item] + $this->formOptions());
    }

    public function update(InventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $item->update($request->validated());

        return redirect()->route('inventory-items.show', $item)->with('success', "Item '{$item->name}' was updated.");
    }

    public function updateStatus(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);

        $item->update($validated);

        $action = $validated['status'] === 'active' ? 'activated' : 'deactivated';

        return back()->with('success', "Item '{$item->name}' was {$action}.");
    }

    /** Human-readable labels for item types. */
    public static function typeLabels(): array
    {
        return [
            InventoryItem::TYPE_INGREDIENT => 'Ingredient',
            InventoryItem::TYPE_IN_PREPARATION => 'In Preparation',
            InventoryItem::TYPE_FINISHED_PRODUCT => 'Finished Product',
        ];
    }

    private function formOptions(): array
    {
        return [
            'categories' => InventoryCategory::orderBy('name')->get(),
            'types' => self::typeLabels(),
        ];
    }
}
