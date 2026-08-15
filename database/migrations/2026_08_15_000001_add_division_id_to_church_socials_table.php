<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A social account can now also belong directly to a Divisi itself (not just to one of its
     * Unions) — same nullable-owner-column shape as union_id/conference_id/institution_id added
     * in 2026_07_21_090000_add_organization_owners_to_church_socials_table.php. Uses the
     * 'organisasi' SocialCategory, same as Union/Conference/Institution.
     */
    public function up(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('institution_id')->constrained()->cascadeOnDelete();

            $table->unique(['division_id', 'platform', 'category', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropUnique(['division_id', 'platform', 'category', 'handle']);
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
