<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One uploaded POS export file. Each location has its own POS, so every batch belongs to a location.
 * NOTE: columns may be adjusted once a sample POS export file is available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->string('file_name');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('imported_at')->useCurrent();          // ERD "import_date"
            $table->unsignedInteger('total_records')->default(0);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_import_batches');
    }
};
