<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 4C: support DRAFT (in-progress) stock counts.
 *  - counted_quantity is empty until staff enter it.
 *  - system_quantity and variance are filled in only when the count is COMPLETED
 *    (blind count: staff never see the system quantity while counting).
 *  - completed_at records when the count was finalized.
 *
 * This is a NEW migration (not an edit of the old one) so existing data is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_count_details', function (Blueprint $table) {
            $table->decimal('system_quantity', 12, 3)->nullable()->change();
            $table->decimal('counted_quantity', 12, 3)->nullable()->change();
            $table->decimal('variance', 12, 3)->nullable()->change();
        });

        Schema::table('stock_counts', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        // NOTE: rolling back requires that no detail rows have empty values.
        Schema::table('stock_count_details', function (Blueprint $table) {
            $table->decimal('system_quantity', 12, 3)->nullable(false)->change();
            $table->decimal('counted_quantity', 12, 3)->nullable(false)->change();
            $table->decimal('variance', 12, 3)->nullable(false)->change();
        });
    }
};
