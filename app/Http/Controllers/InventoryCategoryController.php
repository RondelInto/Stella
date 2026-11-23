<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inventory categories (Management only; 'can:manage-inventory-items' in routes).
 * A category can only be deleted while no items use it.
 */
class InventoryCategoryController extends Controller
{
    public function index(): View
    {
        $categories = InventoryCategory::withCount('inventoryItems')->orderBy('name')->get();

        return view('inventory.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('inventory.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $category = InventoryCategory::create($this->validated($request));

        return redirect()->route('inventory-categories.index')->with('success', "Category '{$category->name}' was added.");
    }

    public function edit(InventoryCategory $category): View
    {
        return view('inventory.categories.edit', compact('category'));
    }

    public function update(Request $request, InventoryCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('inventory-categories.index')->with('success', "Category '{$category->name}' was updated.");
    }

    public function destroy(InventoryCategory $category): RedirectResponse
    {
        if ($category->inventoryItems()->exists()) {
            return back()->with('error', "Category '{$category->name}' still has items and cannot be deleted.");
        }

        $category->delete();

        return redirect()->route('inventory-categories.index')->with('success', "Category '{$category->name}' was deleted.");
    }

    private function validated(Request $request, ?InventoryCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
