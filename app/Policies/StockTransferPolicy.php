<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

/**
 * Who may do what with a SPECIFIC transfer.
 * Combines the role permissions (config/permissions.php) with location and status rules.
 *
 * Laravel finds this policy automatically because of its name (StockTransfer -> StockTransferPolicy).
 * Used in controllers as: Gate::authorize('release', $transfer);
 */
class StockTransferPolicy
{
    /** Anyone with view permission at the source or destination location (Management: all). */
    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->can('view-stock-transfers')
            && ($user->canAccessLocation($transfer->source_location_id)
                || $user->canAccessLocation($transfer->destination_location_id));
    }

    /** Commissary staff at the SOURCE location, while still pending. */
    public function update(User $user, StockTransfer $transfer): bool
    {
        return $transfer->isPending()
            && $user->can('prepare-stock-transfers')
            && $user->location_id === $transfer->source_location_id;
    }

    /** Same people who can edit it may release it. */
    public function release(User $user, StockTransfer $transfer): bool
    {
        return $this->update($user, $transfer);
    }

    /** Branch Manager / Branch Staff at the DESTINATION branch, once released. */
    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $transfer->isReleased()
            && $user->can('receive-stock-transfers')
            && $user->location_id === $transfer->destination_location_id;
    }

    /** Pending only (decision T2). Management anywhere; commissary staff at the source. */
    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $transfer->isPending()
            && $user->can('cancel-stock-transfers')
            && ($user->isManagement() || $user->location_id === $transfer->source_location_id);
    }
}
