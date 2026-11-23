<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for recording a production batch (Commissary Staff).
 * Team decision P1: at least one ingredient is required.
 * Team decision P3: only sellable products are produced; ingredients are ingredient or in-preparation items.
 */
class ProductionRecordRequest extends FormRequest
{
    public const MAX_INGREDIENTS = 50;

    public function authorize(): bool
    {
        return $this->user()->can('record-production');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')
                ->where('status', 'active')->whereNotNull('inventory_item_id')],
            'quantity_produced' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'production_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'ingredients' => ['required', 'array', 'min:1', 'max:'.self::MAX_INGREDIENTS],
            'ingredients.*.inventory_item_id' => ['required', 'integer', 'distinct',
                Rule::exists('inventory_items', 'id')->where('status', 'active')
                    ->whereIn('item_type', [InventoryItem::TYPE_INGREDIENT, InventoryItem::TYPE_IN_PREPARATION])],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.exists' => 'Choose an active product that is linked to an inventory item.',
            'production_date.before_or_equal' => 'The production date cannot be in the future.',
            'ingredients.required' => 'Add at least one ingredient used.',
            'ingredients.min' => 'Add at least one ingredient used.',
            'ingredients.max' => 'A production record can have at most '.self::MAX_INGREDIENTS.' ingredients.',
            'ingredients.*.inventory_item_id.distinct' => 'Each ingredient can only be listed once.',
            'ingredients.*.inventory_item_id.exists' => 'Ingredients must be active Ingredient or In Preparation items.',
            'ingredients.*.quantity.gt' => 'Ingredient quantities must be greater than zero.',
            'ingredients.*.quantity.decimal' => 'Quantities can have at most 3 decimal places.',
        ];
    }

    public function attributes(): array
    {
        return ['product_id' => 'product', 'quantity_produced' => 'quantity produced'];
    }
}
