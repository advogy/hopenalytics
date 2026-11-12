<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * For Facebook and Threads, recent_posts_count is how many posts the automatic fetch sampled
     * — never more than 10 (RECENT_POSTS_SAMPLE in Facebook/ThreadsStatsFetcher). The manual
     * stat form used to save its "Jumlah post" (the page's total) into that same column, which
     * the growth score then read as 10 -> 1,900 jumps. Anything above 10 there can only be such
     * a manual total, so it moves to posts_count, where the form now saves it.
     */
    private const SAMPLE_LIMIT = 10;

    public function up(): void
    {
        DB::table('church_stats')
            ->whereIn('church_social_id', DB::table('church_socials')->select('id')->whereIn('platform', ['facebook', 'threads']))
            ->where('recent_posts_count', '>', self::SAMPLE_LIMIT)
            ->whereNull('posts_count')
            ->update([
                'posts_count' => DB::raw('recent_posts_count'),
                'recent_posts_count' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('church_stats')
            ->whereIn('church_social_id', DB::table('church_socials')->select('id')->whereIn('platform', ['facebook', 'threads']))
            ->where('posts_count', '>', self::SAMPLE_LIMIT)
            ->whereNull('recent_posts_count')
            ->update([
                'recent_posts_count' => DB::raw('posts_count'),
                'posts_count' => null,
            ]);
    }
};
