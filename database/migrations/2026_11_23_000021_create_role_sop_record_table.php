<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW pivot: which roles can view which SOP (replaces sop_records.role_id from the ERD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_sop_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_record_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'sop_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_sop_record');
    }
};
