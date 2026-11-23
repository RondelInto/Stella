<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\InventoryTransactionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Transaction History (the audit trail) and the Record Transaction form.
 */
class InventoryTransactionController extends Controller
{
    /**
     * Audit trail: every stock movement the user is allowed to see.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $locationIds = $user->accessibleLocationIds();
        $filters = $request->only(['location', 'search', 'type', 'user', 'from', 'to']);

        $transactions = InventoryTransaction::query()
            ->with(['inventoryItem', 'location', 'user', 'stockTransfer'])
            ->whereIn('location_id', $locationIds)
            ->when($filters['location'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            // The inner where(fn...) groups the OR in brackets, so it can't escape the whereHas condition.
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('inventoryItem', fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%"))))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('transaction_type', $type))
            ->when($filters['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('transaction_date', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('transaction_date', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'locations' => Location::whereIn('id', $locationIds)->orderBy('name')->get(),
            'users' => User::whereIn('id', InventoryTransaction::whereIn('location_id', $locationIds)->select('user_id'))
                ->orderBy('first_name')->get(),
            'types' => self::typeLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        abort_if(! $user->isManagement() && ! $user->location_id, 403, 'Your account is not assigned to a location.');

        return view('inventory.transactions.create', [
            'items' => InventoryItem::active()->orderBy('name')->get(['id', 'item_code', 'name', 'unit_of_measure']),
            'locations' => $user->isManagement()
                ? Location::active()->orderByRaw("type = 'commissary' desc")->orderBy('name')->get()
                : collect([$user->location]),
            'types' => array_intersect_key(self::typeLabels(), array_flip(InventoryTransactionRequest::MANUAL_TYPES)),
            'selectedItemId' => $request->integer('item') ?: null,
        ]);
    }

    public function store(InventoryTransactionRequest $request, InventoryService $inventory): RedirectResponse
    {
        $item = InventoryItem::findOrFail($request->validated('inventory_item_id'));
        $location = $request->location();

        try {
            $transaction = $inventory->record(
                item: $item,
                location: $location,
                type: $request->validated('transaction_type'),
                quantity: $request->validated('quantity'),
                user: $request->user(),
                transactionDate: Carbon::parse($request->validated('transaction_date')),
                remarks: $request->validated('remarks'),
            );
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        $label = self::typeLabels()[$transaction->transaction_type];
        $balance = InventoryService::format(InventoryService::toUnits($transaction->balance_after));

        return redirect()->route('inventory-transactions.create')
            ->with('success', "{$label}: {$item->name} at {$location->name}. New balance: {$balance} {$item->unit_of_measure}.");
    }

    /** Human-readable labels for all transaction types. */
    public static function typeLabels(): array
    {
        return [
            InventoryTransaction::RECEIVED => 'Received',
            InventoryTransaction::ISSUED => 'Issued',
            InventoryTransaction::DAMAGED => 'Damaged / Spoiled',
            InventoryTransaction::TRANSFER_OUT => 'Transfer Out',
            InventoryTransaction::TRANSFER_IN => 'Transfer In',
            InventoryTransaction::PRODUCTION_USE => 'Used in Production',
            InventoryTransaction::PRODUCTION_OUTPUT => 'Production Output',
            InventoryTransaction::COUNT_ADJUSTMENT => 'Count Adjustment',
        ];
    }
}
