<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finished food products sold to customers (e.g. palabok, kakanin).
 * inventory_item_id links the product to its finished-product inventory item;
 * that item's unit_cost is used as the product cost for profitability analysis.
 * pos_product_code is used to match imported POS sales rows to products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('pos_product_code', 50)->nullable()->unique();
            $table->string('pos_product_name');
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
