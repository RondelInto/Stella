<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One execution of a sales analysis (profitability, frequency, clustering, or regression forecast).
 * Results are stored separately from the raw imported sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_by')->constrained('users')->restrictOnDelete();
            $table->enum('analysis_type', ['profitability', 'frequency', 'clustering', 'regression']);
            $table->date('period_start');
            $table->date('period_end');
            $table->json('model_parameters')->nullable();          // e.g. number of clusters
            $table->decimal('accuracy_score', 8, 4)->nullable();    // e.g. R-squared for regression
            $table->timestamp('run_at')->useCurrent();              // ERD "run_date"
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_runs');
    }
};
