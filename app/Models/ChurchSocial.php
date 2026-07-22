<?php

namespace App\Models;

use App\Enums\SocialCategory;
use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChurchSocial extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id', 'person_id', 'union_id', 'conference_id', 'institution_id', 'platform', 'category', 'handle',
        'platform_account_id', 'profile_url', 'is_active', 'is_auto_fetch', 'last_fetched_at', 'last_fetch_status', 'last_fetch_error',
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

    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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
     * Restrict a query to social accounts belonging to a church or person the given
     * user's role/scope may see. A null $user (public presentation pages) is unrestricted —
     * see Church::scopeVisibleTo() for why.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereHas('church', fn (Builder $q2) => $q2->visibleTo($user))
                ->orWhereHas('person', fn (Builder $q2) => $q2->visibleTo($user))
                ->orWhereHas('union', fn (Builder $q2) => $q2->visibleTo($user))
                ->orWhereHas('conference', fn (Builder $q2) => $q2->visibleTo($user))
                ->orWhereHas('institution', fn (Builder $q2) => $q2->visibleTo($user));
        });
    }

    /**
     * Handle for display, e.g. "@handle" — normalizes handles that were already stored with a leading @.
     */
    public function getDisplayHandleAttribute(): string
    {
        return '@'.ltrim($this->handle, '@');
    }

    /**
     * The owning entity's name, whichever of the five owner columns is actually populated.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->church?->name ?? $this->person?->name ?? $this->union?->name
            ?? $this->conference?->name ?? $this->institution?->name ?? '—';
    }

    /**
     * [routeName, routeParam] for "back to where this account is managed" — used by
     * ChurchSocialController::edit()/update()/destroy(), which are shared across all five
     * owner types via the single /socials/{social}/* routes.
     */
    public function manageRoute(): array
    {
        return match (true) {
            $this->church_id !== null => ['churches.socials.index', $this->church],
            $this->person_id !== null => ['people.socials.index', $this->person],
            $this->union_id !== null => ['admin.unions.socials.index', $this->union],
            $this->conference_id !== null => ['admin.conferences.socials.index', $this->conference],
            $this->institution_id !== null => ['admin.institutions.socials.index', $this->institution],
        };
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
