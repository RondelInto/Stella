<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Location management (Management only; protected by 'can:manage-locations' in routes).
 * Locations are never deleted because stock, transfers, and records are tied to them.
 */
class LocationController extends Controller
{
    public function index(): View
    {
        $locations = Location::query()
            ->withCount(['users as active_users_count' => fn ($q) => $q->where('account_status', 'active')])
            ->orderByRaw("type = 'commissary' desc") // commissary first, then branches
            ->orderBy('name')
            ->get();

        return view('locations.index', compact('locations'));
    }

    public function create(): View
    {
        return view('locations.create');
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::create([
            ...$request->validated(),
            'type' => Location::TYPE_BRANCH, // only branches can be added
            'status' => 'active',
        ]);

        return redirect()->route('locations.index')->with('success', "Location '{$location->name}' was added.");
    }

    public function edit(Location $location): View
    {
        $location->loadCount(['users as active_users_count' => fn ($q) => $q->where('account_status', 'active')]);

        return view('locations.edit', compact('location'));
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated()); // type and status are not changed here

        return redirect()->route('locations.index')->with('success', "Location '{$location->name}' was updated.");
    }

    /**
     * Activate or deactivate a location.
     */
    public function updateStatus(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validated['status'] === 'inactive') {
            if ($location->isCommissary()) {
                return back()->with('error', 'The Central Commissary cannot be deactivated.');
            }

            $activeEmployees = $location->users()->where('account_status', 'active')->count();
            if ($activeEmployees > 0) {
                return back()->with('error', "Cannot deactivate '{$location->name}': {$activeEmployees} active employee(s) are still assigned. Reassign or deactivate them first.");
            }
        }

        $location->update(['status' => $validated['status']]);

        $action = $validated['status'] === 'active' ? 'activated' : 'deactivated';

        return back()->with('success', "Location '{$location->name}' was {$action}.");
    }
}
