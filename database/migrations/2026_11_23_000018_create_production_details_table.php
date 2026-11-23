<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingredients (inventory items) used in a production record.
 * Quantities are entered by staff; the system does not invent recipes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_used', 12, 3);
            $table->timestamps();

            // Custom short name: MySQL index names are limited to 64 characters.
            $table->unique(['production_record_id', 'inventory_item_id'], 'production_details_record_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_details');
    }
};
