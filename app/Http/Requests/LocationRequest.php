<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for adding/editing a location (Management only).
 * New locations are always branches: the company has ONE central commissary (Chapter 1).
 * The type of an existing location is never changed, because employee roles depend on it.
 */
class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-locations');
    }

    public function rules(): array
    {
        $location = $this->route('location');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('locations', 'name')->ignore($location?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:30'],
        ];
    }
}
