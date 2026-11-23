<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Predicted production quantities from a regression analysis run.
 * Forecasts are ESTIMATES for planning only, not guaranteed demand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->date('forecast_date');
            $table->decimal('predicted_quantity', 12, 3);
            $table->timestamps();

            $table->unique(['analysis_run_id', 'product_id', 'location_id', 'forecast_date'], 'forecasts_run_product_location_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_forecasts');
    }
};
