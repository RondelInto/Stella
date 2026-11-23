<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockCount;
use App\Models\StockCountDetail;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Closing stock counts (Module 4C).
 *
 * Team decisions:
 *  - Q3: mistakes in transactions are corrected through counts.
 *  - Q4: BLIND counts: staff never see the system quantity while counting.
 *  - Q5: the first count at a location sets the opening balances.
 *  - B1: completing a count corrects stock automatically (count_adjustment) and keeps the variance.
 *
 * Who: record-stock-counts at their OWN location. Viewing: anyone with view-inventory (own location;
 * Management sees all).
 */
class StockCountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $locationIds = $user->accessibleLocationIds();
        $filters = $request->only(['location', 'status']);

        $counts = StockCount::query()
            ->with(['location', 'countedBy'])
            ->withCount([
                'details',
                'details as discrepancies_count' => fn ($q) => $q->whereNotNull('variance')->where('variance', '!=', 0),
            ])
            ->whereIn('location_id', $locationIds)
            ->when($filters['location'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('count_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.counts.index', [
            'counts' => $counts,
            'filters' => $filters,
            'locations' => Location::whereIn('id', $locationIds)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->location_id, 403, 'Your account is not assigned to a location.');

        return view('inventory.counts.create', [
            'location' => $user->location,
            'categories' => InventoryCategory::withCount(['inventoryItems as active_items_count' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('name')->get(),
            'items' => InventoryItem::active()->orderBy('name')->get(['id', 'item_code', 'name', 'unit_of_measure']),
            'maxItems' => StockCount::MAX_ITEMS,
        ]);
    }

    /**
     * Create a DRAFT count with the chosen items (by category and/or individually).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->location_id, 403, 'Your account is not assigned to a location.');

        $validated = $request->validate([
            'count_date' => ['required', 'date', 'before_or_equal:today'],
            'categories' => ['array'],
            'categories.*' => ['integer', Rule::exists('inventory_categories', 'id')],
            'items' => ['array'],
            'items.*' => ['integer', Rule::exists('inventory_items', 'id')->where('status', 'active')],
            'remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'count_date.before_or_equal' => 'The count date cannot be in the future.',
        ]);

        $itemIds = InventoryItem::active()
            ->where(fn ($q) => $q
                ->whereIn('inventory_category_id', $validated['categories'] ?? [])
                ->orWhereIn('id', $validated['items'] ?? []))
            ->orderBy('name')
            ->pluck('id');

        if ($itemIds->isEmpty()) {
            return back()->withInput()->withErrors(['items' => 'Select at least one category or item to count.']);
        }

        if ($itemIds->count() > StockCount::MAX_ITEMS) {
            return back()->withInput()->withErrors(['items' => "This count has {$itemIds->count()} items. The maximum is "
                .StockCount::MAX_ITEMS.' per count. Please split it into smaller counts (for example, one per category).']);
        }

        $count = DB::transaction(function () use ($user, $validated, $itemIds) {
            $count = StockCount::create([
                'location_id' => $user->location_id,
                'counted_by' => $user->id,
                'count_date' => $validated['count_date'],
                'status' => StockCount::STATUS_DRAFT,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $count->details()->createMany($itemIds->map(fn ($id) => ['inventory_item_id' => $id])->all());

            return $count;
        });

        return redirect()->route('stock-counts.show', $count)
            ->with('success', "Count started with {$itemIds->count()} item(s). Enter the quantities you physically count.");
    }

    /**
     * Draft  -> blind entry form (no system quantities shown).
     * Completed -> results with system quantity, counted quantity, and variance.
     */
    public function show(Request $request, StockCount $count): View
    {
        $user = $request->user();
        abort_unless($user->canAccessLocation($count->location_id), 403);

        $count->load(['location', 'countedBy', 'details.inventoryItem.category']);

        return view('inventory.counts.show', [
            'count' => $count,
            'details' => $count->details->sortBy(fn ($d) => [$d->inventoryItem->category->name, $d->inventoryItem->name]),
            'canEdit' => $count->isDraft() && $this->canRecordAt($user, $count),
        ]);
    }

    /**
     * Save entered quantities (draft). With action=complete, also finalize the count.
     */
    public function update(Request $request, StockCount $count, InventoryService $inventory): RedirectResponse
    {
        $user = $request->user();
        abort_unless($count->isDraft() && $this->canRecordAt($user, $count), 403);

        $validated = $request->validate([
            'counts' => ['array'],
            'counts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'action' => ['required', 'in:save,complete'],
        ], [
            'counts.*.min' => 'Counted quantities cannot be negative.',
            'counts.*.numeric' => 'Counted quantities must be numbers.',
            'counts.*.decimal' => 'Counted quantities can have at most 3 decimal places.',
        ]);

        // Save draft quantities (only for detail rows that belong to THIS count).
        $entered = $validated['counts'] ?? [];
        foreach ($count->details as $detail) {
            if (array_key_exists($detail->id, $entered)) {
                $value = $entered[$detail->id];
                $detail->update(['counted_quantity' => ($value === null || $value === '') ? null : $value]);
            }
        }

        if ($validated['action'] === 'save') {
            return back()->with('success', 'Progress saved. You can continue this count later.');
        }

        return $this->complete($count, $user, $inventory);
    }

    /** Delete a draft count (no stock has changed yet). */
    public function destroy(Request $request, StockCount $count): RedirectResponse
    {
        abort_unless($count->isDraft() && $this->canRecordAt($request->user(), $count), 403);

        $count->delete(); // details are deleted by the database (cascade)

        return redirect()->route('stock-counts.index')->with('success', 'The draft count was deleted. No stock was changed.');
    }

    /**
     * Discrepancy list: every counted item whose physical count differed from the system.
     */
    public function discrepancies(Request $request): View
    {
        $user = $request->user();
        $locationIds = $user->accessibleLocationIds();
        $filters = $request->only(['location', 'search', 'from', 'to']);

        $details = StockCountDetail::query()
            ->with(['inventoryItem', 'stockCount.location', 'stockCount.countedBy'])
            ->whereNotNull('variance')
            ->where('variance', '!=', 0)
            ->whereHas('stockCount', fn ($q) => $q
                ->where('status', StockCount::STATUS_COMPLETED)
                ->whereIn('location_id', $locationIds)
                ->when($filters['location'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('count_date', '>=', $from))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('count_date', '<=', $to)))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('inventoryItem', fn ($q) => $q
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.counts.discrepancies', [
            'details' => $details,
            'filters' => $filters,
            'locations' => Location::whereIn('id', $locationIds)->orderBy('name')->get(),
        ]);
    }

    /**
     * Finalize: compare with the system (locked rows), correct stock, store variances.
     * Everything happens in ONE database transaction.
     */
    private function complete(StockCount $count, $user, InventoryService $inventory): RedirectResponse
    {
        $missing = $count->details()->whereNull('counted_quantity')->count();
        if ($missing > 0) {
            return back()->with('error', "{$missing} item(s) have no counted quantity yet. Enter every item (use 0 if none) before completing.");
        }

        $result = DB::transaction(function () use ($count, $user, $inventory) {
            // Lock the count itself so it can't be completed twice (e.g. double-click).
            $locked = StockCount::lockForUpdate()->find($count->id);
            if (! $locked->isDraft()) {
                return null;
            }

            $discrepancies = 0;
            foreach ($locked->details()->with('inventoryItem')->get() as $detail) {
                $outcome = $inventory->adjustToCount(
                    $detail->inventoryItem,
                    $locked->location,
                    $detail->counted_quantity,
                    $user,
                    $locked->id,
                    "Stock count #{$locked->id} ({$locked->count_date->format('M d, Y')})",
                );

                $detail->update(['system_quantity' => $outcome['system'], 'variance' => $outcome['variance']]);

                if ((float) $outcome['variance'] !== 0.0) {
                    $discrepancies++;
                }
            }

            $locked->update(['status' => StockCount::STATUS_COMPLETED, 'completed_at' => Carbon::now()]);

            return $discrepancies;
        });

        if ($result === null) {
            return redirect()->route('stock-counts.show', $count)->with('error', 'This count was already completed.');
        }

        $message = $result === 0
            ? 'Count completed. All quantities matched the system.'
            : "Count completed. {$result} item(s) had a discrepancy; stock was corrected to the counted quantities.";

        return redirect()->route('stock-counts.show', $count)->with('success', $message);
    }

    private function canRecordAt($user, StockCount $count): bool
    {
        return $user->can('record-stock-counts') && $user->location_id === $count->location_id;
    }
}
