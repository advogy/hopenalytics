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
        Schema::table('church_stats', function (Blueprint $table) {
            // Instagram: aggregated from the ~12 most recent posts returned by the profile scraper (not a lifetime total).
            $table->unsignedInteger('recent_reels_count')->nullable()->after('posts_count');
            $table->unsignedBigInteger('recent_reels_views')->nullable()->after('recent_reels_count');

            // TikTok: aggregated from the N most recent videos fetched alongside the profile (not a lifetime total).
            $table->unsignedInteger('recent_video_count')->nullable()->after('recent_reels_views');
            $table->unsignedBigInteger('recent_video_plays')->nullable()->after('recent_video_count');
            $table->unsignedBigInteger('recent_video_shares')->nullable()->after('recent_video_plays');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('church_stats', function (Blueprint $table) {
            $table->dropColumn(['recent_reels_count', 'recent_reels_views', 'recent_video_count', 'recent_video_plays', 'recent_video_shares']);
        });
    }
};
