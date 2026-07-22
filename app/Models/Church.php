<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Church extends Model
{
    use HasFactory;

    protected $fillable = ['conference_id', 'name', 'slug', 'city', 'logo_url', 'is_active', 'latitude', 'longitude', 'geocoded_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'geocoded_at' => 'datetime',
    ];

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function socials(): HasMany
    {
        return $this->hasMany(ChurchSocial::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Restrict a query to churches the given user's role/scope may see. Self-registered
     * members (role === null) never see any church — their only access is their own Person.
     * A null $user means an unauthenticated caller (e.g. the public presentation pages,
     * which are meant to be shown on a screen with no one logged in) — unrestricted,
     * since every other caller of this scope is behind the `auth` middleware and always
     * has a real user by the time it runs.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query;
        }

        if ($user->role === null) {
            return $query->whereRaw('1 = 0');
        }

        return match (true) {
            $user->role->hasNasionalAccess() || $user->role->level() === 'nasional' => $query,
            $user->role->level() === 'uni' => $query->whereHas(
                'conference', fn (Builder $q) => $q->where('union_id', $user->union_id)
            ),
            $user->role->level() === 'daerah' => $query->where('conference_id', $user->conference_id),
            $user->role->level() === 'gereja' => $query->where('id', $user->church_id),
            // institusi has no reachable Churches at all — Institution isn't nested in this
            // chain (see UserRole::level()).
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
