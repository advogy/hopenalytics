<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A social account can now also belong directly to a Union, Conference, or Institution
     * (e.g. an official Instagram for "Uni Indonesia Kawasan Barat" itself, not any one church
     * under it) — per the user's explicit call. Same nullable-owner-column shape as
     * church_id/person_id; exactly one owner column is ever populated per row. All three use
     * the 'organisasi' SocialCategory (see App\Enums\SocialCategory) rather than gereja/umum/
     * personal, since they aren't church- or person-owned.
     */
    public function up(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->foreignId('union_id')->nullable()->after('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conference_id')->nullable()->after('union_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->after('conference_id')->constrained()->cascadeOnDelete();

            $table->unique(['union_id', 'platform', 'category', 'handle']);
            $table->unique(['conference_id', 'platform', 'category', 'handle']);
            $table->unique(['institution_id', 'platform', 'category', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropUnique(['union_id', 'platform', 'category', 'handle']);
            $table->dropUnique(['conference_id', 'platform', 'category', 'handle']);
            $table->dropUnique(['institution_id', 'platform', 'category', 'handle']);

            $table->dropConstrainedForeignId('union_id');
            $table->dropConstrainedForeignId('conference_id');
            $table->dropConstrainedForeignId('institution_id');
        });
    }
};
