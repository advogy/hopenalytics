<?php

namespace App\Policies;

use App\Models\AdminSuggestion;
use App\Models\User;

class AdminSuggestionPolicy
{
    /**
     * Deliberately NOT the generic UserPolicy::promote() gate — that one only ever lets an actor
     * promote exactly one level below their own (Daerah → Gereja), with a "bootstrap" exception
     * carved out for just SuperAdmin/Admin Global/Admin Nasional. That would leave Admin Uni and
     * Admin Divisi able to SEE a suggestion (via AdminSuggestion::scopeVisibleTo()) but never
     * approve one — the opposite of the user's own explicit call ("admin mission dan diatasnya",
     * i.e. Daerah — "Mission" in Adventist English usage — and every level above it, not just
     * Daerah itself). So this mirrors scopeVisibleTo()'s own reach exactly instead: visible to
     * review implies eligible to review, same as ConferencePolicy::update() is already
     * "visibleTo() + a role check" for its own entity. Confirming a suggestion is a narrower,
     * already-vetted action than freely assigning any role to any unassigned member, which is
     * why this is intentionally wider than the general promote() policy rather than reusing it.
     */
    public function review(User $user, AdminSuggestion $suggestion): bool
    {
        if ($user->role === null || $user->role->isReadOnly()) {
            return false;
        }

        return match (true) {
            $user->role->hasGlobalAccess() || $user->role->level() === 'global' => true,
            $user->role->level() === 'nasional' => in_array($suggestion->conference->union_id, $user->assignedUnionIds(), true),
            $user->role->level() === 'divisi' => $suggestion->conference->union?->division_id === $user->division_id,
            $user->role->level() === 'uni' => $suggestion->conference->union_id === $user->union_id,
            $user->role->level() === 'daerah' => $suggestion->conference_id === $user->conference_id,
            default => false,
        };
    }
}
