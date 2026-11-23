<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Production Planning: remaining finished-product stock per location, to help decide
 * how much to produce (Chapter 2: production is planned from the previous day's leftovers).
 *
 * Team decision P4: Management AND Commissary Staff see all locations here (read-only,
 * finished products only). Branch Managers see only their own branch.
 */
class ProductionPlanningController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $date = Carbon::parse($request->input('date', today()->toDateString()));
        if ($date->isFuture()) {
            $date = today();
        }

        $seesAllLocations = $user->isManagement() || $user->location?->isCommissary();
        $locations = Location::query()
            ->when(! $seesAllLocations, fn ($q) => $q->where('id', $user->location_id))
            ->where(fn ($q) => $q->where('status', 'active')->orWhere('id', $user->location_id))
            ->orderByRaw("type = 'commissary' desc")
            ->orderBy('name')
            ->get();

        $products = Product::active()->whereNotNull('inventory_item_id')->with('inventoryItem')
            ->orderBy('pos_product_name')->get();

        // [inventory_item_id][location_id] => quantity on hand (current)
        $stocks = InventoryStock::whereIn('inventory_item_id', $products->pluck('inventory_item_id'))
            ->whereIn('location_id', $locations->pluck('id'))
            ->get()
            ->groupBy('inventory_item_id')
            ->map(fn ($rows) => $rows->pluck('quantity_on_hand', 'location_id'));

        // Quantity produced per product on the chosen date.
        $produced = ProductionRecord::where('status', ProductionRecord::STATUS_COMPLETED)
            ->whereDate('production_date', $date)
            ->selectRaw('product_id, SUM(quantity_produced) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        return view('production.planning', compact('locations', 'products', 'stocks', 'produced', 'date', 'seesAllLocations'));
    }
}
