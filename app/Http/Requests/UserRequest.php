<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Validation for creating and updating employee accounts (Management only).
 *
 * Business rules:
 *  - Management (Admin) oversees all locations, so an admin-only account has no location.
 *  - Commissary Staff must be assigned to the commissary.
 *  - Branch Managers and Branch Staff must be assigned to a branch.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    /** Normalize input before validating (trim username, empty email -> null). */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => $this->filled('email') ? strtolower(trim($this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var User|null $editing */
        $editing = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => [
                'required', 'string', 'min:3', 'max:50',
                'regex:/^[a-z0-9._-]+$/', // letters, numbers, dot, underscore, dash
                Rule::unique('users', 'username')->ignore($editing?->id),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing?->id)],
            'contact_no' => ['nullable', 'string', 'max:30'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            // Must be an active location (or the user's current one, if unchanged).
            'location_id' => [
                'nullable', 'integer',
                Rule::exists('locations', 'id')->where(fn ($q) => $q
                    ->where('status', 'active')
                    ->orWhere('id', $editing?->location_id)),
            ],
            // Password is required only when creating a new account.
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain lowercase letters, numbers, dots, underscores, and dashes.',
            'roles.required' => 'Please assign at least one role.',
        ];
    }

    /**
     * Extra checks that depend on several fields (role + location).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $slugs = Role::whereIn('id', $this->input('roles', []))->pluck('slug');
                $operationalSlugs = $slugs->reject(fn ($slug) => $slug === Role::ADMIN);

                // Admin-only accounts do not need a location.
                if ($operationalSlugs->isEmpty()) {
                    return;
                }

                $location = Location::find($this->input('location_id'));

                if (! $location) {
                    $validator->errors()->add('location_id', 'Please select the location where this employee works.');

                    return;
                }

                if ($slugs->contains(Role::COMMISSARY_STAFF) && $location->type !== Location::TYPE_COMMISSARY) {
                    $validator->errors()->add('location_id', 'Commissary Staff must be assigned to the Central Commissary.');
                }

                $branchRoles = [Role::BRANCH_MANAGER, Role::BRANCH_STAFF];
                if ($slugs->intersect($branchRoles)->isNotEmpty() && $location->type !== Location::TYPE_BRANCH) {
                    $validator->errors()->add('location_id', 'Branch Managers and Branch Staff must be assigned to a branch.');
                }
            },
        ];
    }

    /** The location to save: admin-only accounts are not tied to a location. */
    public function locationIdToSave(): ?int
    {
        $slugs = Role::whereIn('id', $this->validated('roles'))->pluck('slug');

        return $slugs->reject(fn ($slug) => $slug === Role::ADMIN)->isEmpty()
            ? null
            : (int) $this->validated('location_id');
    }
}
