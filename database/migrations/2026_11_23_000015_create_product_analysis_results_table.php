<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product results of an analysis run.
 * unit_cost_used is a snapshot of the item's unit_cost at the time of analysis,
 * so old results don't change when costs are updated later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('total_quantity_sold', 14, 3)->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('unit_cost_used', 12, 2)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->decimal('gross_profit', 14, 2)->nullable();
            $table->decimal('profit_margin', 8, 4)->nullable();      // e.g. 0.3500 = 35%
            $table->unsignedInteger('sales_frequency')->default(0);  // number of sales transactions
            $table->string('cluster_label', 50)->nullable();
            $table->timestamps();

            $table->unique(['analysis_run_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_analysis_results');
    }
};
