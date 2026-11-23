<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StockTransferRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Stock transfers: commissary -> branch (Module 5).
 * Permissions: config/permissions.php + StockTransferPolicy (location and status rules).
 */
class StockTransferController extends Controller
{
    public function __construct(private StockTransferService $transfers)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $locationIds = $user->accessibleLocationIds();
        $filters = $request->only(['status', 'location', 'from', 'to', 'search']);

        $transfers = StockTransfer::query()
            ->with(['sourceLocation', 'destinationLocation', 'preparedBy'])
            ->withCount('details')
            ->involvingLocations($locationIds)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['location'] ?? null, fn ($q, $id) => $q->involvingLocations([(int) $id]))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('transfer_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('transfer_date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('reference_no', 'like', "%{$s}%"))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Branch users: how many transfers are waiting for them to receive.
        $awaitingReceipt = $user->can('receive-stock-transfers') && $user->location_id
            ? StockTransfer::where('destination_location_id', $user->location_id)->where('status', StockTransfer::STATUS_RELEASED)->count()
            : 0;

        return view('transfers.index', [
            'transfers' => $transfers,
            'filters' => $filters,
            'locations' => Location::whereIn('id', $locationIds)->orderBy('name')->get(),
            'awaitingReceipt' => $awaitingReceipt,
        ]);
    }

    public function create(Request $request): View
    {
        $source = $this->sourceLocationFor($request);

        return view('transfers.create', $this->formData($source));
    }

    public function store(StockTransferRequest $request): RedirectResponse
    {
        $source = $this->sourceLocationFor($request);

        $transfer = $this->transfers->create(
            $request->user(),
            $source,
            Location::findOrFail($request->validated('destination_location_id')),
            $request->validated('transfer_date'),
            $request->validated('lines'),
            $request->validated('remarks'),
        );

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->reference_no} was created as PENDING. Release it when the items leave the commissary.");
    }

    public function show(StockTransfer $transfer): View
    {
        Gate::authorize('view', $transfer);

        $transfer->load(['sourceLocation', 'destinationLocation', 'preparedBy', 'releasedBy', 'receivedBy', 'details.inventoryItem']);

        // While pending, show how much the commissary currently has of each item.
        $available = $transfer->isPending()
            ? InventoryStock::where('location_id', $transfer->source_location_id)
                ->whereIn('inventory_item_id', $transfer->details->pluck('inventory_item_id'))
                ->pluck('quantity_on_hand', 'inventory_item_id')
            : collect();

        return view('transfers.show', compact('transfer', 'available'));
    }

    public function edit(StockTransfer $transfer): View
    {
        Gate::authorize('update', $transfer);

        $transfer->load('details.inventoryItem');

        return view('transfers.edit', ['transfer' => $transfer] + $this->formData($transfer->sourceLocation));
    }

    public function update(StockTransferRequest $request, StockTransfer $transfer): RedirectResponse
    {
        Gate::authorize('update', $transfer);

        try {
            $this->transfers->update(
                $transfer,
                Location::findOrFail($request->validated('destination_location_id')),
                $request->validated('transfer_date'),
                $request->validated('lines'),
                $request->validated('remarks'),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('stock-transfers.show', $transfer)->with('error', $e->getMessage());
        }

        return redirect()->route('stock-transfers.show', $transfer)->with('success', "Transfer {$transfer->reference_no} was updated.");
    }

    public function release(Request $request, StockTransfer $transfer): RedirectResponse
    {
        Gate::authorize('release', $transfer);

        try {
            $this->transfers->release($transfer, $request->user());
        } catch (InsufficientStockException $e) {
            return back()->with('error', 'Cannot release: '.$e->getMessage().' Nothing was released.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Transfer {$transfer->reference_no} was released. Stock was deducted from the commissary.");
    }

    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        Gate::authorize('receive', $transfer);

        $transfer->load('details.inventoryItem');

        $request->validate([
            'received' => ['required', 'array'],
            'received.*.quantity_received' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'received.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'received.*.quantity_received.required' => 'Enter the quantity received for every item (0 if nothing arrived).',
            'received.*.quantity_received.min' => 'Received quantities cannot be negative.',
        ]);

        // Business rules per item (decisions T3 and T4).
        $input = $request->input('received');
        $errors = [];
        $received = [];

        foreach ($transfer->details as $detail) {
            $row = $input[$detail->id] ?? null;
            if ($row === null) {
                $errors["received.{$detail->id}.quantity_received"] = "Enter the quantity received for {$detail->inventoryItem->name}.";

                continue;
            }

            $sent = InventoryService::toUnits($detail->quantity_sent);
            $got = InventoryService::toUnits($row['quantity_received']);
            $remarks = trim((string) ($row['remarks'] ?? ''));

            if ($got > $sent) {
                $errors["received.{$detail->id}.quantity_received"] = "{$detail->inventoryItem->name}: received cannot be more than sent. Note any extra items in the remarks.";
            } elseif ($got !== $sent && $remarks === '') {
                $errors["received.{$detail->id}.remarks"] = "{$detail->inventoryItem->name}: please give a reason for the difference.";
            }

            $received[$detail->id] = ['quantity_received' => $row['quantity_received'], 'remarks' => $remarks ?: null];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $this->transfers->receive($transfer, $request->user(), $received);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Transfer {$transfer->reference_no} was received. Stock was added to your branch.");
    }

    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        Gate::authorize('cancel', $transfer);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->transfers->cancel($transfer, $validated['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Transfer {$transfer->reference_no} was cancelled. No stock was moved.");
    }

    /** Printable transfer slip with signature lines (decision T6). */
    public function print(StockTransfer $transfer): View
    {
        Gate::authorize('view', $transfer);

        $transfer->load(['sourceLocation', 'destinationLocation', 'preparedBy', 'releasedBy', 'receivedBy', 'details.inventoryItem']);

        return view('transfers.print', compact('transfer'));
    }

    /** Transfers are prepared at the user's own location, which must be the commissary (decision T1). */
    private function sourceLocationFor(Request $request): Location
    {
        $location = $request->user()->location;

        abort_unless($location && $location->isCommissary(), 403, 'Only commissary staff can prepare transfers.');

        return $location;
    }

    private function formData(Location $source): array
    {
        return [
            'source' => $source,
            'branches' => Location::active()->where('type', Location::TYPE_BRANCH)->orderBy('name')->get(),
            'items' => InventoryItem::active()->orderBy('name')->get(['id', 'item_code', 'name', 'unit_of_measure']),
            'stock' => InventoryStock::where('location_id', $source->id)->pluck('quantity_on_hand', 'inventory_item_id'),
            'maxItems' => StockTransfer::MAX_ITEMS,
        ];
    }
}
