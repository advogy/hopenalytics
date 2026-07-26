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
            $table->string('cs_whatsapp_number')->nullable()->after('auto_fetch_time');
            $table->string('cs_whatsapp_group_link')->nullable()->after('cs_whatsapp_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['cs_whatsapp_number', 'cs_whatsapp_group_link']);
        });
    }
};
