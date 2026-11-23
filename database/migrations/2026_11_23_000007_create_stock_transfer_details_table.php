<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items and quantities in a stock transfer.
 * A difference between quantity_sent and quantity_received is a transfer discrepancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_sent', 12, 3);
            $table->decimal('quantity_received', 12, 3)->nullable(); // filled in when the branch confirms
            $table->string('remarks')->nullable();                   // reason for any difference
            $table->timestamps();

            // Custom short name: MySQL index names are limited to 64 characters.
            $table->unique(['stock_transfer_id', 'inventory_item_id'], 'transfer_details_transfer_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_details');
    }
};
