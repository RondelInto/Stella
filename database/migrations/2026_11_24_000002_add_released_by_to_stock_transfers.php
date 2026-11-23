<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 5 (team decision T5): record WHO released a transfer.
 * The person who prepares a transfer may be different from the person who releases it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('released_by')->nullable()->after('prepared_by')
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
        });
    }
};
