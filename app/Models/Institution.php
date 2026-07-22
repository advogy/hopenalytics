<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = ['union_id', 'conference_id', 'name', 'slug', 'is_active', 'city', 'latitude', 'longitude', 'geocoded_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'geocoded_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function socials(): HasMany
    {
        return $this->hasMany(ChurchSocial::class);
    }

    /**
     * Both nullable — an institution can sit directly under a Uni, further under one of that
     * Uni's Daerah, or under neither (a nasional institution, applicable to every union), per
     * the user's explicit call. conference_id always implies union_id is set too (denormalized
     * here rather than derived via a join to conferences) so scopeVisibleTo()/InstitutionPolicy
     * can filter on union_id alone.
     */
    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    /**
     * Everyone sees nasional institutions (union_id and conference_id both null — they apply to
     * every union, per the user's explicit call), which is why this isn't the empty-by-default
     * shape the rest of the app's scopeVisibleTo() methods use. Beyond that: uni-level sees
     * everything under their own Uni (both Uni-level and any Daerah-level institution nested
     * under it); daerah-level sees their own Uni's Uni-level institutions (context, same as
     * Union::scopeVisibleTo()) plus their own Daerah's institutions; institusi-level only ever
     * sees the one institution they're bound to, regardless of what level it sits at.
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
            $user->role->level() === 'uni' => $query->where(function (Builder $q) use ($user) {
                $q->where(fn (Builder $q2) => $q2->whereNull('union_id')->whereNull('conference_id')) // nasional
                    ->orWhere('union_id', $user->union_id); // this union's own + every Daerah under it
            }),
            $user->role->level() === 'daerah' => $query->where(function (Builder $q) use ($user) {
                // A daerah-level admin's own union_id is never populated (UserAssignmentController
                // only ever sets conference_id for this level) — derive it via the conference
                // relation instead of trusting $user->union_id, which is always null here.
                $ownUnionId = $user->conference?->union_id;
                $q->where(fn (Builder $q2) => $q2->whereNull('union_id')->whereNull('conference_id')) // nasional
                    ->when($ownUnionId, fn (Builder $q2) => $q2->orWhere(
                        fn (Builder $q3) => $q3->where('union_id', $ownUnionId)->whereNull('conference_id')
                    )) // this union's own
                    ->orWhere('conference_id', $user->conference_id); // this daerah's own
            }),
            $user->role->level() === 'institusi' => $query->where('id', $user->institution_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Narrower than scopeVisibleTo() above: Kelola Akun's Institusi tab uses this instead,
     * per the user's explicit call — what shows in that management list should be exactly what
     * the viewer can act on, not also the nasional institutions visibleTo() surfaces everywhere
     * for read-only context. Mirrors InstitutionPolicy::update() exactly (minus the nasional
     * branch, which is unconditionally true there).
     */
    public function scopeManageableBy(Builder $query, User $user): Builder
    {
        if ($user->role === null) {
            return $query->whereRaw('1 = 0');
        }

        return match (true) {
            $user->role->hasNasionalAccess() || $user->role->level() === 'nasional' => $query,
            $user->role->level() === 'uni' => $query->where('union_id', $user->union_id),
            $user->role->level() === 'daerah' => $query->where('conference_id', $user->conference_id),
            $user->role->level() === 'institusi' => $query->where('id', $user->institution_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
