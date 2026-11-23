<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Employee account management (Management only; protected by 'can:manage-users' in routes).
 * Accounts are never deleted: they are deactivated so the audit trail stays intact.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'role', 'location', 'status']);

        $users = User::query()
            ->with(['activeRoles', 'location'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $roleId) => $query->whereHas(
                'activeRoles', fn ($q) => $q->where('roles.id', $roleId)
            ))
            ->when($filters['location'] ?? null, fn ($query, $locationId) => $query->where('location_id', $locationId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('account_status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::orderBy('id')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', $this->formOptions());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                ...$request->safe()->only(['first_name', 'last_name', 'username', 'email', 'contact_no', 'password']),
                'location_id' => $request->locationIdToSave(),
                'account_status' => 'active',
            ]);

            $user->syncRoleAssignments($request->validated('roles'));

            return $user;
        });

        return redirect()->route('users.index')
            ->with('success', "Employee account '{$user->username}' was created.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user->load(['activeRoles', 'location'])] + $this->formOptions($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $adminRoleId = Role::where('slug', Role::ADMIN)->value('id');
        $keepsAdmin = in_array($adminRoleId, array_map('intval', $request->validated('roles')), true);

        if ($user->isManagement() && ! $keepsAdmin) {
            if ($user->is($request->user())) {
                return back()->withInput()->with('error', 'You cannot remove your own Management role.');
            }
            if ($this->isLastActiveAdmin($user)) {
                return back()->withInput()->with('error', 'This is the last active Management account. Assign another admin first.');
            }
        }

        DB::transaction(function () use ($request, $user) {
            $user->fill([
                ...$request->safe()->only(['first_name', 'last_name', 'username', 'email', 'contact_no']),
                'location_id' => $request->locationIdToSave(),
            ]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            $user->syncRoleAssignments($request->validated('roles'));
        });

        return redirect()->route('users.index')
            ->with('success', "Employee account '{$user->username}' was updated.");
    }

    /**
     * Activate or deactivate an account.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'account_status' => ['required', 'in:active,inactive'],
        ]);

        if ($validated['account_status'] === 'inactive') {
            if ($user->is($request->user())) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }
            if ($user->isManagement() && $this->isLastActiveAdmin($user)) {
                return back()->with('error', 'You cannot deactivate the last active Management account.');
            }
        }

        $user->update(['account_status' => $validated['account_status']]);

        $action = $validated['account_status'] === 'active' ? 'activated' : 'deactivated';

        return back()->with('success', "Account '{$user->username}' was {$action}.");
    }

    /**
     * Management sets a new password for an employee (e.g. forgotten password, no email).
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validateWithBag('resetPassword', [
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => $validated['password']]);

        return back()->with('success', "Password for '{$user->username}' was reset. Give the new password to the employee privately.");
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return ! User::where('id', '!=', $user->id)
            ->where('account_status', 'active')
            ->whereHas('activeRoles', fn ($q) => $q->where('slug', Role::ADMIN))
            ->exists();
    }

    /**
     * Dropdown/checkbox data shared by the create and edit forms.
     * Only active locations are offered, plus the user's current location (even if inactive)
     * so editing an old account doesn't silently clear it.
     */
    private function formOptions(?User $user = null): array
    {
        return [
            'roles' => Role::orderBy('id')->get(),
            'locations' => Location::query()
                ->where(fn ($q) => $q->where('status', 'active')->orWhere('id', $user?->location_id))
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
        ];
    }
}
