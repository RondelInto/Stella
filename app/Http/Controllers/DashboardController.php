<?php

namespace App\Http\Controllers;

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

        return view('dashboard', compact('stats'));
    }
}
