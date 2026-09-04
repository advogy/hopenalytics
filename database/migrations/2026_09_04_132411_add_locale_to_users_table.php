<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Locale was previously session-only (see SetLocale/LocaleController) — fine while a user
     * only ever reads their own pages, but an email another user's action sends them (e.g. Saran
     * Admin's approval mail) has no session of theirs to read at send time, so it fell back to
     * whichever locale the ACTING admin's own browser happened to be in. Persisted here instead,
     * kept in sync by SetLocale on every authenticated request, so any future "email user X"
     * flow can address them in their own last-known language.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
