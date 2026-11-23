<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * View the system roles and edit their descriptions and responsibilities (Management only).
 *
 * Roles cannot be created or deleted here: each role's permissions are defined in
 * config/permissions.php using its fixed slug, so a new role would have no permissions.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['users as active_users_count' => fn ($q) => $q
                ->where('role_user.is_active', true)
                ->where('users.account_status', 'active')])
            ->orderBy('id')
            ->get();

        // Which permissions each role has (read from config/permissions.php).
        $permissionsByRole = $roles->mapWithKeys(fn (Role $role) => [
            $role->id => collect(config('permissions'))
                ->filter(fn (array $slugs) => in_array($role->slug, $slugs, true))
                ->keys(),
        ]);

        return view('roles.index', compact('roles', 'permissionsByRole'));
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
        ]);

        $role->update($validated); // slug is never changed

        return redirect()->route('roles.index')->with('success', "Role '{$role->name}' was updated.");
    }
}
