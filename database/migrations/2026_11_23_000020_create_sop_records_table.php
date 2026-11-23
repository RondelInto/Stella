<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standard Operating Procedures. Content is stored as text (no file uploads).
 * Roles are assigned through the role_sop_record pivot table (one SOP -> many roles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sop_records', function (Blueprint $table) {
            $table->id();
            $table->string('sop_code', 30)->unique();               // e.g. SOP-INV-001
            $table->string('title');
            $table->string('category', 100);
            $table->string('description')->nullable();              // short summary
            $table->longText('content');                            // the full procedure
            $table->string('version_no', 20)->default('1.0');
            $table->date('effective_date')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();                                    // updated_at = ERD "last_updated"

            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_records');
    }
};
