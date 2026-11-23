<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The stock transfer workflow. All stock changes go through InventoryService, so transfers get
 * row locking, negative-stock protection, and audit-trail entries automatically.
 *
 * Every step runs in ONE database transaction: if any item fails, nothing changes.
 */
class StockTransferService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Create a PENDING transfer (no stock moves yet).
     *
     * @param  array<int, array{inventory_item_id: int, quantity: string}>  $lines
     */
    public function create(User $preparedBy, Location $source, Location $destination, string $transferDate, array $lines, ?string $remarks): StockTransfer
    {
        return DB::transaction(function () use ($preparedBy, $source, $destination, $transferDate, $lines, $remarks) {
            $transfer = StockTransfer::create([
                'reference_no' => $this->nextReferenceNo((int) substr($transferDate, 0, 4)),
                'source_location_id' => $source->id,
                'destination_location_id' => $destination->id,
                'prepared_by' => $preparedBy->id,
                'transfer_date' => $transferDate,
                'status' => StockTransfer::STATUS_PENDING,
                'remarks' => $remarks,
            ]);

            $this->saveLines($transfer, $lines);

            return $transfer;
        });
    }

    /** Edit a PENDING transfer: replaces its item lines. */
    public function update(StockTransfer $transfer, Location $destination, string $transferDate, array $lines, ?string $remarks): void
    {
        DB::transaction(function () use ($transfer, $destination, $transferDate, $lines, $remarks) {
            $locked = $this->lockWithStatus($transfer, StockTransfer::STATUS_PENDING);

            $locked->update([
                'destination_location_id' => $destination->id,
                'transfer_date' => $transferDate,
                'remarks' => $remarks,
            ]);

            $locked->details()->delete();
            $this->saveLines($locked, $lines);
        });
    }

    /**
     * RELEASE: stock leaves the source location (transfer_out for every item).
     * Throws InsufficientStockException if any item doesn't have enough stock; nothing is saved then.
     */
    public function release(StockTransfer $transfer, User $releasedBy): void
    {
        DB::transaction(function () use ($transfer, $releasedBy) {
            $locked = $this->lockWithStatus($transfer, StockTransfer::STATUS_PENDING);
            $locked->load(['details.inventoryItem', 'sourceLocation', 'destinationLocation']);

            foreach ($locked->details as $detail) {
                $this->inventory->record(
                    $detail->inventoryItem,
                    $locked->sourceLocation,
                    InventoryTransaction::TRANSFER_OUT,
                    $detail->quantity_sent,
                    $releasedBy,
                    remarks: "{$locked->reference_no} to {$locked->destinationLocation->name}",
                    links: ['stock_transfer_id' => $locked->id],
                );
            }

            $locked->update([
                'status' => StockTransfer::STATUS_RELEASED,
                'released_by' => $releasedBy->id,
                'released_at' => now(),
            ]);
        });
    }

    /**
     * RECEIVE: stock arrives at the destination with the quantities actually received.
     * Items received as 0 create no stock movement (the shortage stays visible as a discrepancy).
     *
     * @param  array<int, array{quantity_received: string, remarks: ?string}>  $received  keyed by detail ID
     */
    public function receive(StockTransfer $transfer, User $receivedBy, array $received): void
    {
        DB::transaction(function () use ($transfer, $receivedBy, $received) {
            $locked = $this->lockWithStatus($transfer, StockTransfer::STATUS_RELEASED);
            $locked->load(['details.inventoryItem', 'sourceLocation', 'destinationLocation']);

            foreach ($locked->details as $detail) {
                $quantity = $received[$detail->id]['quantity_received'];

                $detail->update([
                    'quantity_received' => $quantity,
                    'remarks' => $received[$detail->id]['remarks'] ?? null,
                ]);

                if (InventoryService::toUnits($quantity) > 0) {
                    $this->inventory->record(
                        $detail->inventoryItem,
                        $locked->destinationLocation,
                        InventoryTransaction::TRANSFER_IN,
                        $quantity,
                        $receivedBy,
                        remarks: "{$locked->reference_no} from {$locked->sourceLocation->name}",
                        links: ['stock_transfer_id' => $locked->id],
                    );
                }
            }

            $locked->update([
                'status' => StockTransfer::STATUS_RECEIVED,
                'received_by' => $receivedBy->id,
                'received_at' => now(),
            ]);
        });
    }

    /** CANCEL: only while pending, so no stock has moved (decision T2). */
    public function cancel(StockTransfer $transfer, ?string $reason): void
    {
        DB::transaction(function () use ($transfer, $reason) {
            $locked = $this->lockWithStatus($transfer, StockTransfer::STATUS_PENDING);

            $locked->update([
                'status' => StockTransfer::STATUS_CANCELLED,
                'remarks' => trim(($locked->remarks ? $locked->remarks."\n" : '').'Cancelled: '.($reason ?: 'no reason given')),
            ]);
        });
    }

    /** Lock the transfer row and make sure it is still in the expected status (prevents double-clicks). */
    private function lockWithStatus(StockTransfer $transfer, string $expected): StockTransfer
    {
        $locked = StockTransfer::lockForUpdate()->findOrFail($transfer->id);

        if ($locked->status !== $expected) {
            throw new RuntimeException("Transfer {$locked->reference_no} is already {$locked->status}.");
        }

        return $locked;
    }

    private function saveLines(StockTransfer $transfer, array $lines): void
    {
        foreach ($lines as $line) {
            $transfer->details()->create([
                'inventory_item_id' => $line['inventory_item_id'],
                'quantity_sent' => $line['quantity'],
            ]);
        }
    }

    /** Next number for the year: TR-2026-0001, TR-2026-0002, ... */
    private function nextReferenceNo(int $year): string
    {
        $prefix = "TR-{$year}-";

        $last = StockTransfer::where('reference_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('reference_no')
            ->value('reference_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
