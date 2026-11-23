<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for sellable products (Management only).
 * Each product is linked to ONE finished-product inventory item:
 *  - production adds stock to that item,
 *  - that item's unit_cost is the product cost for profitability (team decision Q3).
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory-items');
    }

    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('pos_product_code'));
        $this->merge(['pos_product_code' => $code === '' ? null : $code]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'pos_product_name' => ['required', 'string', 'max:255'],
            'pos_product_code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'pos_product_code')->ignore($product?->id)],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'inventory_item_id' => [
                'required', 'integer',
                Rule::exists('inventory_items', 'id')->where('item_type', InventoryItem::TYPE_FINISHED_PRODUCT),
                Rule::unique('products', 'inventory_item_id')->ignore($product?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.exists' => 'Choose an inventory item whose type is "Finished Product".',
            'inventory_item_id.unique' => 'That inventory item is already linked to another product.',
            'pos_product_code.unique' => 'Another product already uses this POS code.',
        ];
    }

    public function attributes(): array
    {
        return [
            'pos_product_name' => 'product name',
            'pos_product_code' => 'POS product code',
            'inventory_item_id' => 'linked inventory item',
        ];
    }
}
