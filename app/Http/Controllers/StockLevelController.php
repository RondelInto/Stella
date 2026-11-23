<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stock Levels: quantity on hand per item and location.
 *  - Management: one location, or all locations side by side.
 *  - Everyone else: only their own location.
 */
class StockLevelController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['location', 'search', 'category', 'type', 'low']);

        $locations = Location::whereIn('id', $user->accessibleLocationIds())
            ->orderByRaw("type = 'commissary' desc")
            ->orderBy('name')
            ->get();

        // Which location(s) to show. Non-management users always see only their own.
        $selected = $user->isManagement()
            ? $locations->firstWhere('id', (int) ($filters['location'] ?? 0))
            : $locations->first();
        $shownLocations = $selected ? collect([$selected]) : $locations;

        $items = InventoryItem::query()
            ->active()
            ->with('category')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%")))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('inventory_category_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('item_type', $type))
            // "Low stock only" works when ONE location is shown (stock <= reorder level there).
            ->when(($filters['low'] ?? false) && $selected, fn ($q) => $q->whereRaw(
                'COALESCE((SELECT quantity_on_hand FROM inventory_stocks s
                           WHERE s.inventory_item_id = inventory_items.id AND s.location_id = ?), 0)
                 <= inventory_items.reorder_level',
                [$selected->id]
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Stock for only the items on this page: [item_id][location_id] => quantity
        $stocks = InventoryStock::whereIn('inventory_item_id', $items->pluck('id'))
            ->whereIn('location_id', $shownLocations->pluck('id'))
            ->get()
            ->groupBy('inventory_item_id')
            ->map(fn ($rows) => $rows->pluck('quantity_on_hand', 'location_id'));

        return view('inventory.stock.index', [
            'items' => $items,
            'stocks' => $stocks,
            'locations' => $locations,
            'shownLocations' => $shownLocations,
            'selected' => $selected,
            'filters' => $filters,
            'categories' => InventoryCategory::orderBy('name')->get(),
            'types' => InventoryItemController::typeLabels(),
            'isManagement' => $user->isManagement(),
        ]);
    }
}
