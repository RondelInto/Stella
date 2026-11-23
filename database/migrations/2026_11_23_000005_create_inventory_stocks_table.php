<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current stock of each item at each location.
 * IMPORTANT: quantity_on_hand must only change together with an inventory_transactions row
 * (handled by a service class in Phase 4), never edited directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->timestamps(); // updated_at = ERD "last_updated"

            $table->unique(['location_id', 'inventory_item_id']); // one row per item per location
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
