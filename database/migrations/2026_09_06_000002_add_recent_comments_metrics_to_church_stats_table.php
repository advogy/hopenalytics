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
            // Same "recent sample, not a lifetime total" convention as their sibling
            // recent_*_views/recent_*_shares columns — Instagram/TikTok/Facebook already fetch
            // a per-post comment count for other purposes (hashtag matching), this just also
            // sums it the same way likes/views/shares already are.
            $table->unsignedBigInteger('recent_reels_comments')->nullable()->after('recent_reels_views');
            $table->unsignedBigInteger('recent_video_comments')->nullable()->after('recent_video_shares');
            $table->unsignedBigInteger('recent_posts_comments')->nullable()->after('recent_posts_shares');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('church_stats', function (Blueprint $table) {
            $table->dropColumn(['recent_reels_comments', 'recent_video_comments', 'recent_posts_comments']);
        });
    }
};
