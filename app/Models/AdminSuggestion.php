<?php

namespace App\Models;

use App\Enums\AdminSuggestionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's claim, made by typing a not-yet-existing Gereja name during Lengkapi Profil (or
 * later, Profil Saya's Wilayah section — see FindsOrCreatesChurch::findExistingChurchOrSuggestAdmin()),
 * that they should become that new church's admin — held for review rather than auto-creating
 * the Church and promoting them outright, per the user's explicit call. Approving one creates
 * the Church (see Admin\AdminSuggestionController::approve()) and promotes the requester to
 * admin_gereja over it, exactly like a manual Kelola Pengguna promotion; rejecting one leaves
 * the requester a plain member (role stays null) and creates no Church at all.
 */
class AdminSuggestion extends Model
{
    protected $fillable = [
        'user_id', 'person_id', 'conference_id', 'church_name',
        'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'resulting_church_id',
    ];

    protected $casts = [
        'status' => AdminSuggestionStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resultingChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'resulting_church_id');
    }

    /**
     * Same reach as Conference::scopeVisibleTo() — a suggestion for Conference X is visible to
     * whoever can already see/manage Conference X, keyed directly off this row's own
     * conference_id rather than a join. "admin mission dan diatasnya" per the user's own
     * framing: Daerah (Conference-level — "Mission" in Adventist English usage) and every level
     * above it; Gereja/Institusi-level admins and a plain member never see any row here, same as
     * they never see Conference rows either.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->role === null) {
            return $query->whereRaw('1 = 0');
        }

        return match (true) {
            $user->role->hasGlobalAccess() || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->whereHas('conference', fn (Builder $q) => $q->whereIn('union_id', $user->assignedUnionIds())),
            $user->role->level() === 'divisi' => $query->whereHas('conference.union', fn (Builder $q) => $q->where('division_id', $user->division_id)),
            $user->role->level() === 'uni' => $query->whereHas('conference', fn (Builder $q) => $q->where('union_id', $user->union_id)),
            $user->role->level() === 'daerah' => $query->where('conference_id', $user->conference_id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
