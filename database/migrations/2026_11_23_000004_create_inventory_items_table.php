<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory items: ingredients, items still being prepared, and finished products.
 * unit_cost is also used as the product cost in profitability analysis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained()->restrictOnDelete();
            $table->string('item_code', 50)->unique();
            $table->string('name');
            $table->enum('item_type', ['ingredient', 'in_preparation', 'finished_product']);
            $table->string('unit_of_measure', 30);                  // e.g. kg, pcs, bilao, liter
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('name'); // fast search across ~1,000+ items
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
