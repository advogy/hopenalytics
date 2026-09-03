<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Defaults OFF, unlike every other platform's own flag (all default true) — Threads
            // support relies on an as-yet-unverified Apify actor response shape (no real Apify
            // token was available to test against while building this), so it ships disabled
            // until a superadmin confirms a real fetch works, then flips it on themselves from
            // Settings' platform toggle card.
            $table->boolean('threads_enabled')->default(false)->after('x_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('threads_enabled');
        });
    }
};
