<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Institutions can now optionally sit under a Uni, or further under a Daerah within that
     * Uni, instead of always being nasional-wide — per the user's explicit call: some
     * institutions belong to one union, some to one conference, some apply to every union
     * (left null on both). conference_id implies union_id is set too (denormalized rather than
     * derived via join) so scopeVisibleTo()/policies can filter on union_id alone without
     * joining through conferences — see Institution::scopeVisibleTo().
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('union_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('conference_id')->nullable()->after('union_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conference_id');
            $table->dropConstrainedForeignId('union_id');
        });
    }
};
