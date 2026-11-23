<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for adding/editing inventory items (Management only).
 */
class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory-items');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'item_code' => strtoupper(trim((string) $this->input('item_code'))),
            'unit_of_measure' => trim((string) $this->input('unit_of_measure')),
        ]);
    }

    public function rules(): array
    {
        $item = $this->route('item');

        $rules = self::fieldRules();
        $rules['item_code'][] = Rule::unique('inventory_items', 'item_code')->ignore($item?->id);
        $rules['inventory_category_id'] = ['required', 'integer', Rule::exists('inventory_categories', 'id')];

        return $rules;
    }

    /**
     * Field rules shared by this form AND the CSV importer (App\Services\InventoryItemImporter),
     * so both always validate items the same way.
     */
    public static function fieldRules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['required', Rule::in([
                InventoryItem::TYPE_INGREDIENT,
                InventoryItem::TYPE_IN_PREPARATION,
                InventoryItem::TYPE_FINISHED_PRODUCT,
            ])],
            'unit_of_measure' => ['required', 'string', 'max:30'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public static function fieldMessages(): array
    {
        return [
            'item_code.regex' => 'The item code may only contain letters, numbers, dots, underscores, and dashes (no spaces).',
            'item_type.in' => 'The item type must be ingredient, in_preparation, or finished_product.',
        ];
    }

    public function attributes(): array
    {
        return [
            'inventory_category_id' => 'category',
            'unit_of_measure' => 'unit',
        ];
    }
}
