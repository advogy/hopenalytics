<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personal-only, per the user's explicit call: pulling an individual's social media stats
     * without their explicit agreement is a real privacy concern (a Church/Institution/
     * Union/Conference/Division is an organization, not a private individual — this column
     * stays permanently null for those, and nothing ever checks it there). Single nullable
     * timestamp, same "presence = flag" idiom as geocoded_at/last_fetched_at already on this
     * table — non-null means consent was given, null means it wasn't (or was revoked).
     *
     * Every existing Personal account gets consent_at = null the moment this ships, which is
     * the whole point: they're automatically excluded from auto-fetch/bulk/manual refresh (see
     * ChurchSocial::scopeConsentGranted()) until the account owner (or an admin managing that
     * Personal) opens its edit form and checks the consent box once.
     */
    public function up(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->timestamp('consent_at')->nullable()->after('is_auto_fetch');
        });
    }

    public function down(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropColumn('consent_at');
        });
    }
};
