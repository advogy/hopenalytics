<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-tunable spacing between each broadcast email's send — same delay()-per-job
     * throttling ChurchRefreshController::all() already uses successfully on this exact
     * Hostinger setup (3s there), just exposed here as a setting rather than hardcoded, since
     * the actual safe rate depends on Hostinger's own outbound limit (not published anywhere
     * we've found — see the "451 ... hostinger_out_ratelimit" incident this was built in
     * response to), which the user needs to confirm with their host and may need to tune without
     * a code change.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('bulk_email_delay_seconds')->default(3)->after('cs_whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('bulk_email_delay_seconds');
        });
    }
};
