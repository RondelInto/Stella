<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\StockTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/editing a transfer (Commissary Staff).
 * Decision T1: only commissary -> branch, so the destination must be an active BRANCH.
 */
class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Fine-grained checks (own location, pending status) are in StockTransferPolicy.
        return $this->user()->can('prepare-stock-transfers');
    }

    public function rules(): array
    {
        return [
            'destination_location_id' => ['required', 'integer', Rule::exists('locations', 'id')
                ->where('type', Location::TYPE_BRANCH)->where('status', 'active')],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:'.StockTransfer::MAX_ITEMS],
            'lines.*.inventory_item_id' => ['required', 'integer', 'distinct',
                Rule::exists('inventory_items', 'id')->where('status', 'active')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_location_id.exists' => 'Please select an active branch.',
            'transfer_date.before_or_equal' => 'The transfer date cannot be in the future.',
            'lines.required' => 'Add at least one item to the transfer.',
            'lines.min' => 'Add at least one item to the transfer.',
            'lines.max' => 'A transfer can have at most '.StockTransfer::MAX_ITEMS.' items.',
            'lines.*.inventory_item_id.distinct' => 'Each item can only appear once in a transfer.',
            'lines.*.inventory_item_id.exists' => 'One of the items is not active.',
            'lines.*.quantity.gt' => 'Quantities must be greater than zero.',
            'lines.*.quantity.decimal' => 'Quantities can have at most 3 decimal places.',
        ];
    }

    public function attributes(): array
    {
        return ['destination_location_id' => 'destination branch'];
    }
}
