<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function unions(): HasMany
    {
        return $this->hasMany(Union::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Mirrors Union::scopeVisibleTo() one tier up: 'nasional' sees whichever Divisions its
     * assigned Unions happen to belong to (Admin Nasional's Union set is independent of
     * Division, see UserRole::level() docblock — this just surfaces the Divisions that set
     * touches), and 'uni' can still *see* its own parent Division for context, the same way
     * a daerah-level admin can see its own parent Uni.
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
            $user->role->level() === 'nasional' => $query->whereHas(
                'unions', fn (Builder $q) => $q->whereIn('id', $user->assignedUnionIds())
            ),
            $user->role->level() === 'divisi' => $query->where('id', $user->division_id),
            $user->role->level() === 'uni' => $query->whereHas(
                'unions', fn (Builder $q) => $q->where('id', $user->union_id)
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
