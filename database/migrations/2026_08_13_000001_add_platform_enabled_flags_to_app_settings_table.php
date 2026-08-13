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
            $table->boolean('youtube_enabled')->default(true)->after('youtube_api_key');
            $table->boolean('instagram_enabled')->default(true)->after('youtube_enabled');
            $table->boolean('tiktok_enabled')->default(true)->after('instagram_enabled');
            $table->boolean('facebook_enabled')->default(true)->after('tiktok_enabled');
            $table->boolean('x_enabled')->default(true)->after('facebook_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['youtube_enabled', 'instagram_enabled', 'tiktok_enabled', 'facebook_enabled', 'x_enabled']);
        });
    }
};
