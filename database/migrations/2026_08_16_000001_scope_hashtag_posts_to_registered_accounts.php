<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hashtag tracking moved from a global platform-wide hashtag search to scanning only
 * already-registered accounts' own recent posts (per the user's explicit call) — every
 * existing hashtag_posts row was sourced under the old global-search behavior and may belong to
 * an account nobody registered, so it's truncated here rather than migrated; matching accounts
 * will repopulate it from their next auto-fetch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hashtag_posts', function (Blueprint $table) {
            $table->foreignId('church_social_id')->nullable()->after('hashtag_id')->constrained()->cascadeOnDelete();
        });

        DB::table('hashtag_posts')->truncate();
    }

    public function down(): void
    {
        Schema::table('hashtag_posts', function (Blueprint $table) {
            $table->dropForeign(['church_social_id']);
            $table->dropColumn('church_social_id');
        });
    }
};
