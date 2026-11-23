<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\ProductionRecordRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionRecord;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Production records (Module 6).
 *  - Record: Commissary Staff at the commissary ('record-production').
 *  - History: everyone with 'view-production' sees ALL records (team decision P5),
 *    because production only happens at the commissary.
 */
class ProductionRecordController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['product', 'from', 'to']);

        $records = ProductionRecord::query()
            ->with(['product.inventoryItem', 'user', 'location'])
            ->withCount('details')
            ->when($filters['product'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('production_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('production_date', '<=', $to))
            ->latest('production_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('production.index', [
            'records' => $records,
            'filters' => $filters,
            'products' => Product::orderBy('pos_product_name')->get(['id', 'pos_product_name']),
        ]);
    }

    public function create(Request $request): View
    {
        $location = $this->commissaryFor($request);

        return view('production.create', [
            'location' => $location,
            'products' => Product::active()->whereNotNull('inventory_item_id')->with('inventoryItem')
                ->orderBy('pos_product_name')->get(),
            'ingredients' => InventoryItem::active()
                ->whereIn('item_type', [InventoryItem::TYPE_INGREDIENT, InventoryItem::TYPE_IN_PREPARATION])
                ->orderBy('name')->get(['id', 'item_code', 'name', 'unit_of_measure']),
            'stock' => InventoryStock::where('location_id', $location->id)->pluck('quantity_on_hand', 'inventory_item_id'),
            'maxIngredients' => ProductionRecordRequest::MAX_INGREDIENTS,
        ]);
    }

    public function store(ProductionRecordRequest $request, ProductionService $production): RedirectResponse
    {
        $location = $this->commissaryFor($request);
        $product = Product::with('inventoryItem')->findOrFail($request->validated('product_id'));

        try {
            $record = $production->record(
                $request->user(),
                $location,
                $product,
                $request->validated('quantity_produced'),
                Carbon::parse($request->validated('production_date')),
                $request->validated('ingredients'),
                $request->validated('remarks'),
            );
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', 'Nothing was saved. '.$e->getMessage());
        }

        $qty = \App\Services\InventoryService::format(\App\Services\InventoryService::toUnits($record->quantity_produced));

        return redirect()->route('production.show', $record)
            ->with('success', "Production recorded: {$qty} {$product->inventoryItem->unit_of_measure} of {$product->pos_product_name}. Ingredients were deducted and the product was added to commissary stock.");
    }

    public function show(ProductionRecord $record): View
    {
        $record->load(['product.inventoryItem', 'user', 'location', 'details.inventoryItem']);

        return view('production.show', compact('record'));
    }

    /** Production is recorded at the user's own location, which must be the commissary. */
    private function commissaryFor(Request $request): Location
    {
        $location = $request->user()->location;

        abort_unless($location && $location->isCommissary(), 403, 'Production is recorded at the commissary.');

        return $location;
    }
}
