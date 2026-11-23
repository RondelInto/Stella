<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard with role-based summary cards.
 * Each module adds its own summary here as it is built. Only real database counts are shown.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $stats = [];

        if ($user->can('manage-users')) {
            $stats['employees'] = [
                'active' => User::where('account_status', 'active')->count(),
                'inactive' => User::where('account_status', 'inactive')->count(),
                'without_role' => User::where('account_status', 'active')->doesntHave('activeRoles')->count(),
                'locations' => Location::active()->count(),
            ];
        }

        if ($user->can('view-inventory')) {
            $locationIds = $user->accessibleLocationIds();

            // Active items at or below their reorder level, per accessible location
            // (items never stocked at a location count as 0).
            $stats['low_stock'] = Location::whereIn('id', $locationIds)
                ->orderByRaw("type = 'commissary' desc")
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location) => [
                    'location' => $location,
                    'count' => InventoryItem::active()->whereRaw(
                        'COALESCE((SELECT quantity_on_hand FROM inventory_stocks s
                                   WHERE s.inventory_item_id = inventory_items.id AND s.location_id = ?), 0)
                         <= inventory_items.reorder_level',
                        [$location->id]
                    )->count(),
                ]);

            $stats['transactions_today'] = InventoryTransaction::whereIn('location_id', $locationIds)
                ->whereDate('transaction_date', today())
                ->count();
        }

        return view('dashboard', compact('stats'));
    }
}
