<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HashtagPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'hashtag_id', 'church_social_id', 'platform', 'external_post_id', 'post_url',
        'author_handle', 'caption', 'likes_count', 'comments_count', 'views_count', 'posted_at',
        'last_seen_at', 'raw_payload',
    ];

    protected $casts = [
        'platform' => SocialPlatform::class,
        'posted_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function hashtag(): BelongsTo
    {
        return $this->belongsTo(Hashtag::class);
    }

    /**
     * The registered account this post came from — nullable only for backward compatibility
     * with the column's own default; every row created since hashtag tracking moved to scanning
     * registered accounts (see MatchAccountHashtags) always sets this.
     */
    public function churchSocial(): BelongsTo
    {
        return $this->belongsTo(ChurchSocial::class);
    }
}
