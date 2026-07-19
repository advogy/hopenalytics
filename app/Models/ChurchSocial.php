<?php

namespace App\Models;

use App\Enums\SocialCategory;
use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChurchSocial extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id', 'person_id', 'platform', 'category', 'handle', 'platform_account_id',
        'profile_url', 'is_active', 'is_auto_fetch', 'last_fetched_at', 'last_fetch_status', 'last_fetch_error',
    ];

    protected $casts = [
        'platform' => SocialPlatform::class,
        'category' => SocialCategory::class,
        'is_active' => 'boolean',
        'is_auto_fetch' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(ChurchStat::class)->orderByDesc('recorded_at');
    }

    public function latestStat(): HasOne
    {
        return $this->hasOne(ChurchStat::class)->latestOfMany('recorded_at');
    }

    /**
     * Handle for display, e.g. "@handle" — normalizes handles that were already stored with a leading @.
     */
    public function getDisplayHandleAttribute(): string
    {
        return '@'.ltrim($this->handle, '@');
    }

    /**
     * The church's name, or the account owner's name for church-independent personal accounts.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->church?->name ?? $this->person?->name ?? '—';
    }

    /**
     * The public profile URL for this account, if one can be determined.
     * Facebook has no reliable handle-based URL pattern, so it requires profile_url to be set.
     */
    public function externalUrl(): ?string
    {
        if ($this->profile_url) {
            return $this->profile_url;
        }

        if (! $this->handle) {
            return null;
        }

        $handle = ltrim($this->handle, '@');

        return match ($this->platform) {
            SocialPlatform::Instagram => "https://www.instagram.com/{$handle}",
            SocialPlatform::TikTok => "https://www.tiktok.com/@{$handle}",
            SocialPlatform::YouTube => "https://www.youtube.com/@{$handle}",
            SocialPlatform::Facebook => null,
        };
    }
}
