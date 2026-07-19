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
        'recent_reels_count', 'recent_reels_views',
        'recent_video_count', 'recent_video_plays', 'recent_video_shares',
        'raw_payload',
    ];

    protected $casts = [
        'recorded_at' => 'date',
        'raw_payload' => 'array',
    ];

    public function churchSocial(): BelongsTo
    {
        return $this->belongsTo(ChurchSocial::class);
    }
}
