<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * THE AUDIT TRAIL: every stock movement, who did it, where, and when.
 * Rows are never edited or deleted; mistakes are corrected with a new transaction.
 *
 * quantity      = always positive; the transaction_type decides if stock goes up or down
 *                 (count_adjustment stores a signed value: + found stock, - missing stock)
 * balance_after = stock level at that location right after this movement
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('transaction_type', [
                'received',           // + stock delivered to a location
                'issued',             // - stock issued for use
                'production_use',     // - ingredient used in production
                'production_output',  // + finished product from production
                'transfer_out',       // - released to another location
                'transfer_in',        // + received from another location
                'damaged',            // - spoiled / damaged stock
                'count_adjustment',   // +/- correction after a closing count
            ]);
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 12, 3);
            // Links to the record that caused this movement (only one is filled, or none).
            $table->foreignId('stock_transfer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_count_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('transaction_date');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'inventory_item_id', 'transaction_date'], 'inv_tx_location_item_date_index');
            $table->index(['transaction_type', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
