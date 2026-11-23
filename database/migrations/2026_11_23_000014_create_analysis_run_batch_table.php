<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: which import batches were used in an analysis run (ERD: Analysis_Run_Batches).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_run_batch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_import_batch_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // Custom short name: MySQL index names are limited to 64 characters.
            $table->unique(['analysis_run_id', 'sales_import_batch_id'], 'analysis_run_batch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_run_batch');
    }
};
