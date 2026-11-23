<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW (not in original ERD): each item counted in a stock count.
 * variance = counted_quantity - system_quantity (negative = missing stock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 12, 3);
            $table->decimal('counted_quantity', 12, 3);
            $table->decimal('variance', 12, 3);
            $table->timestamps();

            $table->unique(['stock_count_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_details');
    }
};
