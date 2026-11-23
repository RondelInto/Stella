<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sellable products (Management only; 'can:manage-inventory-items').
 * Products are deactivated, never deleted, because production and sales records point to them.
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status']);

        $products = Product::query()
            ->with('inventoryItem')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('pos_product_name', 'like', "%{$s}%")
                ->orWhere('pos_product_code', 'like', "%{$s}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('pos_product_name')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', compact('products', 'filters'));
    }

    public function create(): View
    {
        return view('products.create', ['items' => $this->linkableItems()]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated() + ['status' => 'active']);

        return redirect()->route('products.index')->with('success', "Product '{$product->pos_product_name}' was added.");
    }

    public function edit(Product $product): View
    {
        return view('products.edit', ['product' => $product, 'items' => $this->linkableItems($product)]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')->with('success', "Product '{$product->pos_product_name}' was updated.");
    }

    public function updateStatus(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);
        $product->update($validated);

        $action = $validated['status'] === 'active' ? 'activated' : 'deactivated';

        return back()->with('success', "Product '{$product->pos_product_name}' was {$action}.");
    }

    /** Finished-product items not yet linked to another product (plus this product's own item). */
    private function linkableItems(?Product $product = null)
    {
        return InventoryItem::query()
            ->where('item_type', InventoryItem::TYPE_FINISHED_PRODUCT)
            ->where(fn ($q) => $q
                ->whereDoesntHave('product')
                ->orWhere('id', $product?->inventory_item_id))
            ->orderBy('name')
            ->get(['id', 'item_code', 'name', 'unit_of_measure', 'status']);
    }
}
