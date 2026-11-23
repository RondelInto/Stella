<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW (not in original ERD): a closing inventory count session at one location.
 * Used to detect stock discrepancies (system quantity vs. physically counted quantity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('counted_by')->constrained('users')->restrictOnDelete();
            $table->date('count_date');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'count_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
    }
};
