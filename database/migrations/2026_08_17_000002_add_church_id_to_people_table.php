<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets Person become the single place a self-registered member's reported region lives
 * (Uni/Daerah/Gereja), instead of splitting it across users.union_id/conference_id/church_id
 * (which double as an assigned admin's own scope once promoted — see
 * CompleteProfileController) and people.union_id/conference_id. Mirrors union_id/conference_id's
 * own nullOnDelete() convention on this same table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable()->after('conference_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('church_id');
        });
    }
};
