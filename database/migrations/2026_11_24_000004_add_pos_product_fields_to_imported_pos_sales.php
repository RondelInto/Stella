<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 7 (decision PI4): keep the product code and name exactly as the POS file wrote them,
 * so rows that matched no product can be grouped and linked to a product later by Admin.
 * (The full original row is still kept in raw_data.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_pos_sales', function (Blueprint $table) {
            $table->string('pos_product_code', 50)->nullable()->after('product_id');
            $table->string('pos_product_name')->after('pos_product_code');

            $table->index(['product_id', 'pos_product_name'], 'imported_pos_sales_unmatched_index');
        });
    }

    public function down(): void
    {
        Schema::table('imported_pos_sales', function (Blueprint $table) {
            $table->dropIndex('imported_pos_sales_unmatched_index');
            $table->dropColumn(['pos_product_code', 'pos_product_name']);
        });
    }
};
