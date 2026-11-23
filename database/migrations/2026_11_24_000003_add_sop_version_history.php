<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9 (team decision S2): SOP version history.
 *  - sop_records.version_no becomes a whole number (1, 2, 3...) that increases automatically
 *    when an ACTIVE SOP's procedure is changed.
 *  - sop_versions keeps a read-only copy of every replaced version.
 *  - sop_records.updated_by records who last changed the SOP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sop_records', function (Blueprint $table) {
            $table->unsignedInteger('version_no')->default(1)->change();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
        });

        Schema::create('sop_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_record_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('title');
            $table->string('description')->nullable();
            $table->longText('content');
            $table->date('effective_date')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete(); // who wrote THIS version
            $table->timestamps();

            $table->unique(['sop_record_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_versions');

        Schema::table('sop_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->string('version_no', 20)->default('1.0')->change();
        });
    }
};
