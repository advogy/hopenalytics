<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unions', function (Blueprint $table) {
            // Nullable: a Union doesn't have to belong to a Division yet — existing Unions
            // start unassigned rather than being force-migrated into a placeholder Division.
            $table->foreignId('division_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('unions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
