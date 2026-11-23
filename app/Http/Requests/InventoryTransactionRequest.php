<?php

namespace App\Http\Requests;

use App\Models\InventoryTransaction;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for manually recorded stock movements (Received / Issued / Damaged).
 * Transfers, production, and counts create their own transactions in later modules.
 *
 * Team decision (Module 4 Q1): users record at their OWN location; Management at any location.
 */
class InventoryTransactionRequest extends FormRequest
{
    /** Types that can be recorded manually on the Record Transaction page. */
    public const MANUAL_TYPES = [
        InventoryTransaction::RECEIVED,
        InventoryTransaction::ISSUED,
        InventoryTransaction::DAMAGED,
    ];

    public function authorize(): bool
    {
        return $this->user()->can('record-inventory-transactions');
    }

    /** Non-Management users always record at their own location (form value is ignored). */
    protected function prepareForValidation(): void
    {
        if (! $this->user()->isManagement()) {
            $this->merge(['location_id' => $this->user()->location_id]);
        }
    }

    public function rules(): array
    {
        return [
            'transaction_type' => ['required', Rule::in(self::MANUAL_TYPES)],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('status', 'active')],
            'inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->where('status', 'active')],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'transaction_date' => ['required', 'date', 'before_or_equal:now'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'location_id.required' => 'Your account is not assigned to a location. Please contact management.',
            'location_id.exists' => 'Please select an active location.',
            'inventory_item_id.exists' => 'Please select an active item.',
            'quantity.gt' => 'The quantity must be greater than zero.',
            'transaction_date.before_or_equal' => 'The date and time cannot be in the future.',
        ];
    }

    public function attributes(): array
    {
        return [
            'inventory_item_id' => 'item',
            'location_id' => 'location',
            'transaction_type' => 'type',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $locationId = (int) $this->input('location_id');
                if ($locationId && ! $this->user()->canAccessLocation($locationId)) {
                    $validator->errors()->add('location_id', 'You can only record transactions for your assigned location.');
                }
            },
        ];
    }

    public function location(): Location
    {
        return Location::findOrFail($this->validated('location_id'));
    }
}
