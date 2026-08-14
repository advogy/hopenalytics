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
     * Strictly region-scoped — same shape as Church::scopeVisibleTo(), per the user's explicit
     * call: admin_uni/admin_daerah should only ever see institutions in their own wilayah, not
     * also nasional institutions or (for daerah-level) their whole parent Uni "for context" the
     * way an earlier version of this scope did. uni-level sees every institution under their own
     * Uni (both Uni-level and any Daerah-level institution nested under it, since a Daerah is
     * part of "their own wilayah" too); daerah-level sees only their own Daerah's institutions;
     * institusi-level only ever sees the one institution they're bound to, regardless of what
     * level it sits at. Now identical in shape to scopeManageableBy() below for every non-null-
     * user branch — kept as separate methods since visibleTo() also has to handle the public
     * (null $user) and unassigned-member cases manageableBy() never needs to.
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
            // Admin/Pimpinan Nasional: institutions under their assigned Union set, PLUS
            // every "nasional" institution (no union_id at all — decision above) — per the
            // user's explicit call, a nasional institution stays visible to every Admin
            // Nasional regardless of which Unions they're assigned to, not just Admin Global.
            $user->role->level() === 'nasional' => $query->where(
                fn (Builder $q) => $q->whereIn('union_id', $user->assignedUnionIds())->orWhereNull('union_id')
            ),
            // Unlike 'nasional' above, no orWhereNull() carve-out for nasional institutions —
            // same strict, region-only reasoning as the 'uni' arm right below (an
            // admin_uni/admin_daerah only sees institutions in their own wilayah, not also
            // nasional ones; Divisi is one tier up from Uni and stays just as strict).
            $user->role->level() === 'divisi' => $query->whereHas('union', fn (Builder $q) => $q->where('division_id', $user->division_id)),
            $user->role->level() === 'uni' => $query->where('union_id', $user->union_id),
            $user->role->level() === 'daerah' => $query->where('conference_id', $user->conference_id),
            $user->role->level() === 'institusi' => $query->where('id', $user->institution_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Same shape as scopeVisibleTo() above for every non-null-user branch (the two now agree —
     * see that method's docblock for why they're still kept separate). Kelola Akun's Institusi
     * tab uses this one specifically since it never needs the public/unassigned-member cases.
     * Mirrors InstitutionPolicy::update() exactly (minus the nasional branch, which is
     * unconditionally true there).
     */
    public function scopeManageableBy(Builder $query, User $user): Builder
    {
        if ($user->role === null) {
            return $query->whereRaw('1 = 0');
        }

        return match (true) {
            $user->role->hasGlobalAccess() || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->where(
                fn (Builder $q) => $q->whereIn('union_id', $user->assignedUnionIds())->orWhereNull('union_id')
            ),
            // Unlike 'nasional' above, no orWhereNull() carve-out for nasional institutions —
            // same strict, region-only reasoning as the 'uni' arm right below (an
            // admin_uni/admin_daerah only sees institutions in their own wilayah, not also
            // nasional ones; Divisi is one tier up from Uni and stays just as strict).
            $user->role->level() === 'divisi' => $query->whereHas('union', fn (Builder $q) => $q->where('division_id', $user->division_id)),
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
