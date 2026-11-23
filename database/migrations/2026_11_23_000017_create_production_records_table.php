<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily food production (usually at the commissary).
 * Saving a record adds the finished product to stock and deducts the ingredients used
 * (handled by a service class in Phase 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();   // who recorded it
            $table->foreignId('production_forecast_id')->nullable()->constrained()->nullOnDelete();
            $table->date('production_date');
            $table->decimal('quantity_produced', 12, 3);
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'production_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_records');
    }
};
