<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_social_id', 'recorded_at', 'subscribers_count', 'followers_count',
        'following_count', 'likes_count', 'views_count', 'videos_count', 'posts_count',
        'recent_reels_count', 'recent_reels_views', 'recent_reels_comments',
        'recent_video_count', 'recent_video_plays', 'recent_video_shares', 'recent_video_comments',
        'recent_posts_count', 'recent_posts_likes', 'recent_posts_shares', 'recent_posts_comments',
        'raw_payload',
    ];

    /**
     * Every column except raw_payload — the full API response kept per snapshot (~16KB each),
     * which nothing reads back once stored. Dashboard/analytics reads select just these, since
     * dragging raw_payload along for every account made those pages noticeably slower.
     */
    public const SUMMARY_COLUMNS = [
        'id', 'church_social_id', 'recorded_at', 'subscribers_count', 'followers_count',
        'following_count', 'likes_count', 'views_count', 'videos_count', 'posts_count',
        'recent_reels_count', 'recent_reels_views', 'recent_reels_comments',
        'recent_video_count', 'recent_video_plays', 'recent_video_shares', 'recent_video_comments',
        'recent_posts_count', 'recent_posts_likes', 'recent_posts_shares', 'recent_posts_comments',
        'created_at', 'updated_at',
    ];

    public static function summaryColumns(string $table = 'church_stats'): array
    {
        return array_map(fn ($column) => "{$table}.{$column}", self::SUMMARY_COLUMNS);
    }

    protected $casts = [
        'recorded_at' => 'date',
        'raw_payload' => 'array',
    ];

    public function churchSocial(): BelongsTo
    {
        return $this->belongsTo(ChurchSocial::class);
    }
}
