<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw sales rows imported from the external POS. These are NOT created by this system
 * and must never be edited. raw_data keeps the original row exactly as it appeared in the file.
 * NOTE: columns may be adjusted once a sample POS export file is available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_import_batch_id')->constrained()->cascadeOnDelete();
            // NULL when the POS product could not be matched to a product in this system yet.
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('pos_receipt_no', 100)->nullable();
            $table->dateTime('sale_datetime');
            $table->decimal('quantity_sold', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sale_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_pos_sales');
    }
};
