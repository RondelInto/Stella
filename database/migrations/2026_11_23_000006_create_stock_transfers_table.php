<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock transfers between locations (usually commissary -> branch).
 * Workflow: pending -> released (stock leaves source) -> received (stock arrives at destination)
 *           pending -> cancelled (no stock moved)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();          // e.g. TR-2026-0001
            $table->foreignId('source_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('transfer_date');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('received_at')->nullable();          // ERD "date_received"
            $table->enum('status', ['pending', 'released', 'received', 'cancelled'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
