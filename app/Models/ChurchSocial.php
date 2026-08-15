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
        'church_id', 'person_id', 'union_id', 'conference_id', 'institution_id', 'division_id', 'platform', 'category', 'handle',
        'platform_account_id', 'profile_url', 'is_active', 'is_auto_fetch', 'last_fetched_at', 'last_fetch_status', 'last_fetch_error',
    ];

    protected $casts = [
        'platform' => SocialPlatform::class,
        'category' => SocialCategory::class,
        'is_active' => 'boolean',
        'is_auto_fetch' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * A superadmin-disabled platform (see AppSetting::enabledPlatformValues(), Settings'
     * platform-visibility card) must disappear from every query built off this model —
     * cards, history, growth score, Kelola Akun counts, directory, the auto-fetch command's
     * own query — without having to filter each of those ~15 call sites individually.
     * Bypass with ChurchSocial::withoutGlobalScope('enabledPlatform') where a true,
     * unfiltered count is genuinely needed (only Settings' own card, to show "N akun"
     * next to a platform regardless of whether it's currently enabled).
     *
     * Does NOT reach raw ->join('church_socials', ...) queries rooted in a different
     * model (see ChurchDashboardController::growthOverTime()) — those need their own
     * explicit ->whereIn('church_socials.platform', ...) alongside this scope.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('enabledPlatform', fn (Builder $query) => $query->whereIn(
            'platform',
            AppSetting::current()->enabledPlatformValues(),
        ));
    }

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

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
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
                ->orWhereHas('institution', fn (Builder $q2) => $q2->visibleTo($user))
                ->orWhereHas('division', fn (Builder $q2) => $q2->visibleTo($user));
        });
    }

    /**
     * Restrict to accounts whose owning entity — whichever of the five it is — is itself
     * active. Previously several callers (the weekly auto-fetch dispatch, bulk refresh, "needs
     * attention") each re-wrote this checking church/person only, silently skipping every
     * institution/union/conference-owned account even when it was flagged is_auto_fetch — see
     * FetchAllChurchStats.
     */
    public function scopeOwnerActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('church', fn (Builder $q2) => $q2->where('is_active', true))
            ->orWhereHas('person', fn (Builder $q2) => $q2->where('is_active', true))
            ->orWhereHas('institution', fn (Builder $q2) => $q2->where('is_active', true))
            ->orWhereHas('union', fn (Builder $q2) => $q2->where('is_active', true))
            ->orWhereHas('conference', fn (Builder $q2) => $q2->where('is_active', true))
            ->orWhereHas('division', fn (Builder $q2) => $q2->where('is_active', true)));
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
            ?? $this->conference?->name ?? $this->institution?->name ?? $this->division?->name ?? '—';
    }

    /**
     * Label for whichever of the five owner columns is actually populated — "Gereja"/"Personal"/
     * "Uni"/"Daerah"/"Institusi", used wherever a list mixes owner types together (e.g. the
     * "Akun Otomatis" list) and needs to say which is which alongside the name itself.
     */
    public function getOwnerTypeLabelAttribute(): string
    {
        return match (true) {
            $this->church_id !== null => __('common.church'),
            $this->person_id !== null => __('common.personal'),
            $this->union_id !== null => __('common.union'),
            $this->conference_id !== null => __('common.conference'),
            $this->institution_id !== null => __('common.institution'),
            $this->division_id !== null => __('common.division'),
            default => '—',
        };
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
            $this->division_id !== null => ['admin.divisions.socials.index', $this->division],
        };
    }

    /**
     * [routeName, entity] for the owner's own public "show"/history page — used by
     * SocialStatController's manual-entry form and its redirect-on-save, which are likewise
     * shared across all five owner types via the single /socials/{social}/stats/* routes.
     * Same owner-column match as manageRoute() above, just pointed at each type's read-only
     * show page (unions.show/conferences.show) instead of its socials-management page.
     */
    public function showRoute(): array
    {
        return match (true) {
            $this->church_id !== null => ['churches.show', $this->church],
            $this->person_id !== null => ['people.show', $this->person],
            $this->union_id !== null => ['unions.show', $this->union],
            $this->conference_id !== null => ['conferences.show', $this->conference],
            $this->institution_id !== null => ['institutions.show', $this->institution],
            $this->division_id !== null => ['divisions.show', $this->division],
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
            SocialPlatform::X => "https://x.com/{$handle}",
        };
    }
}
