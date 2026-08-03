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
            // Facebook: aggregated from the N most recent posts fetched via the posts scraper
            // (not a lifetime total — Facebook exposes no lifetime post count via scraping,
            // unlike Instagram's postsCount/TikTok's video count).
            $table->unsignedInteger('recent_posts_count')->nullable()->after('recent_video_shares');
            $table->unsignedBigInteger('recent_posts_likes')->nullable()->after('recent_posts_count');
            $table->unsignedBigInteger('recent_posts_shares')->nullable()->after('recent_posts_likes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('church_stats', function (Blueprint $table) {
            $table->dropColumn(['recent_posts_count', 'recent_posts_likes', 'recent_posts_shares']);
        });
    }
};
