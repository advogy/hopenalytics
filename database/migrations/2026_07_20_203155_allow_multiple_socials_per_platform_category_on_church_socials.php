<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A church/person can now have more than one account of the same platform+category
     * (e.g. an official Instagram and a separate youth-ministry Instagram, both "gereja") —
     * the old (church_id|person_id, platform, category) unique only ever allowed one. Widened
     * to include handle instead of dropped outright, so a genuine accidental duplicate (the
     * exact same handle submitted twice) is still blocked.
     *
     * (church_id, platform, category) has no unique index left to drop here — it was already
     * gone by the time this migration was written (only a plain, non-unique church_id index
     * remains, added by an earlier migration precisely so this day would come without an FK
     * headache). person_id's equivalent still needs its own standalone index added first —
     * MySQL refuses to drop a unique index an FK is still leaning on for lookups.
     */
    public function up(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropUnique(['person_id', 'platform', 'category']);
            $table->unique(['church_id', 'platform', 'category', 'handle']);
            $table->unique(['person_id', 'platform', 'category', 'handle']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropUnique(['church_id', 'platform', 'category', 'handle']);
            $table->dropUnique(['person_id', 'platform', 'category', 'handle']);
            $table->unique(['person_id', 'platform', 'category']);
        });
    }
};
