<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conference extends Model
{
    use HasFactory;

    protected $fillable = ['union_id', 'name', 'slug', 'is_active', 'latitude', 'longitude'];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    /** Same scoping shape as Church::scopeVisibleTo(). */
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
            $user->role->level() === 'nasional' => $query->whereIn('union_id', $user->assignedUnionIds()),
            $user->role->level() === 'divisi' => $query->whereHas('union', fn (Builder $q) => $q->where('division_id', $user->division_id)),
            $user->role->level() === 'uni' => $query->where('union_id', $user->union_id),
            $user->role->level() === 'daerah' => $query->where('id', $user->conference_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function socials(): HasMany
    {
        return $this->hasMany(ChurchSocial::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
