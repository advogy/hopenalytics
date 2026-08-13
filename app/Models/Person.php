<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use HasFactory;

    protected $fillable = ['union_id', 'conference_id', 'user_id', 'name', 'is_active', 'city', 'latitude', 'longitude', 'geocoded_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'geocoded_at' => 'datetime',
    ];

    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socials(): HasMany
    {
        return $this->hasMany(ChurchSocial::class);
    }

    /**
     * Restrict a query to people the given user's role/scope may see. A Person with no
     * union/conference (independent, or a not-yet-assigned member) is only visible at
     * nasional level — never lost, but never shown to a narrower-scoped admin either.
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
            $user->role->hasGlobalAccess() || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->where(function (Builder $q) use ($user) {
                $unionIds = $user->assignedUnionIds();
                $q->whereIn('union_id', $unionIds)
                    ->orWhereHas('conference', fn (Builder $q2) => $q2->whereIn('union_id', $unionIds));
            }),
            $user->role->level() === 'uni' => $query->where(function (Builder $q) use ($user) {
                $q->where('union_id', $user->union_id)
                    ->orWhereHas('conference', fn (Builder $q2) => $q2->where('union_id', $user->union_id));
            }),
            $user->role->level() === 'daerah' => $query->where('conference_id', $user->conference_id),
            // gereja/institusi have no reachable People at all — Institution isn't nested in
            // this chain (see UserRole::level()), and a gereja-level admin's one church isn't
            // a Person-scoping unit here.
            default => $query->whereRaw('1 = 0'),
        };
    }
}
