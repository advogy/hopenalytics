<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Union;
use App\Models\User;

class UserPolicy
{
    /**
     * Whether $actor may assign $newRole (with the given org-unit $scopeId, or — only for
     * $newRole->level() === 'nasional' — the Union-id set $scopeIds) to $target. Promotion is
     * delegated by level (decision #5): an actor can only promote into the one level directly
     * below their own, and only within their own org unit — with exceptions: SuperAdmin may
     * bootstrap any level directly (global/nasional/uni/daerah/gereja, no chain needed to seed
     * the first one); Admin Global may likewise bootstrap nasional/uni/daerah/gereja directly
     * (but never global itself — only SuperAdmin creates more Admin/Pimpinan Global, same
     * caution as Admin Nasional never assigning into nasional); Admin Nasional may bootstrap
     * uni/daerah/gereja directly (never global or nasional), scoped to their own assigned Union
     * set. Institusi sits outside the chain entirely (see UserRole::level()) — Admin Global or
     * Admin Nasional may assign it directly (per the user's explicit call — institutions aren't
     * Union-scoped, so Admin Nasional's own scoping doesn't narrow this).
     */
    public function promote(User $actor, User $target, UserRole $newRole, ?int $scopeId, ?array $scopeIds = null): bool
    {
        if ($actor->role === null || $actor->role->isReadOnly()) {
            return false;
        }

        if ($newRole->level() === 'institusi') {
            return ($actor->role->hasGlobalAccess() || $actor->role === UserRole::AdminNasional)
                && $this->scopeExists('institusi', $scopeId);
        }

        if ($actor->role === UserRole::SuperAdmin) {
            if ($newRole->level() === 'nasional') {
                return $this->unionIdsExist($scopeIds);
            }

            return $this->scopeExists($newRole->level(), $scopeId);
        }

        if ($actor->role === UserRole::AdminGlobal) {
            if ($newRole->level() === 'global') {
                return false;
            }

            if ($newRole->level() === 'nasional') {
                return $this->unionIdsExist($scopeIds);
            }

            return $this->scopeExists($newRole->level(), $scopeId);
        }

        if ($actor->role === UserRole::AdminNasional) {
            if (in_array($newRole->level(), ['global', 'nasional'], true)) {
                return false;
            }

            return $this->scopeBelongsToAssignedUnions($actor, $newRole->level(), $scopeId);
        }

        if ($actor->role->promotesToLevel() !== $newRole->level()) {
            return false;
        }

        return $this->scopeBelongsToActor($actor, $newRole->level(), $scopeId);
    }

    /**
     * Revocation is scope-based, not tied to whoever originally promoted the user (decision
     * #6) — any current actor at the right level, over the target's current org unit, may
     * revoke or reassign it.
     */
    public function revoke(User $actor, User $target): bool
    {
        if ($target->role === null) {
            return false;
        }

        return $this->manageable($actor, $target);
    }

    /**
     * Delete/deactivate/resend-OTP share the same scoping rules as revoke, except they must
     * also work on unassigned members (role === null) and on institusi-level targets, which
     * sit outside the nasional→uni→daerah→gereja chain (see UserRole::level()).
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->manageable($actor, $target);
    }

    public function toggleActive(User $actor, User $target): bool
    {
        return $this->manageable($actor, $target);
    }

    public function resendOtp(User $actor, User $target): bool
    {
        return $this->manageable($actor, $target);
    }

    /** Editing a target's login/display name (User.name) shares the same scoping as the other row actions. */
    public function update(User $actor, User $target): bool
    {
        return $this->manageable($actor, $target);
    }

    /**
     * Clearing a target's union_id/conference_id/church_id — same scoping as the other row
     * actions, and deliberately not restricted to unassigned (role === null) targets the way
     * revoke() is: an active Admin/Pimpinan's region is the same field, per the user's explicit
     * call this can be released independently of (and without) revoking their role, e.g. to
     * clean up a bogus self-reported church without touching their assignment. See
     * UserAssignmentController::releaseRegion().
     */
    public function releaseRegion(User $actor, User $target): bool
    {
        return $this->manageable($actor, $target);
    }

    private function manageable(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->role === null || $actor->role->isReadOnly()) {
            return false;
        }

        if ($actor->role === UserRole::SuperAdmin) {
            // Superadmin can bootstrap any level directly (see promote() above), so they
            // must also be able to review/fix/revoke what they bootstrapped, regardless
            // of the normal one-level-down delegation chain.
            return true;
        }

        if ($actor->role === UserRole::AdminGlobal) {
            // Never manages SuperAdmin or a peer Admin/Pimpinan Global — same caution as
            // Admin Global never being able to create more Global-tier accounts (promote()).
            return $target->role !== UserRole::SuperAdmin && $target->role?->level() !== 'global';
        }

        if ($actor->role === UserRole::AdminNasional) {
            // Never manages SuperAdmin, Admin/Pimpinan Global, or a peer Admin/Pimpinan
            // Nasional account — same "never touches nasional-or-above" rule as before.
            $targetIsAboveOrPeerNasional = ($target->role?->hasGlobalAccess() ?? false)
                || ($target->role !== null && $target->role->level() === 'nasional');

            if ($targetIsAboveOrPeerNasional) {
                return false;
            }

            // Institusi and unassigned members aren't Union-scoped at all (institutions
            // per the user's explicit call, unassigned members because they have no org
            // unit yet), so they stay reachable regardless of the actor's assigned Unions.
            if ($target->role === null || $target->role->level() === 'institusi') {
                return true;
            }

            return $this->scopeBelongsToAssignedUnions($actor, $target->role->level(), match ($target->role->level()) {
                'uni' => $target->union_id,
                'daerah' => $target->conference_id,
                'gereja' => $target->church_id,
                default => null,
            });
        }

        if ($target->role === null) {
            // Unassigned members aren't scoped to any org unit yet, so anyone who can
            // promote at all may manage them (mirrors the unassigned list itself, which
            // isn't filtered by region either).
            return $actor->role->promotesToLevel() !== null;
        }

        if ($target->role->level() === 'institusi') {
            return $actor->role->hasGlobalAccess() || $actor->role === UserRole::AdminNasional;
        }

        if ($actor->role->promotesToLevel() !== $target->role->level()) {
            return false;
        }

        return match ($target->role->level()) {
            'nasional', 'uni' => true,
            'daerah' => $target->conference_id !== null
                && Conference::whereKey($target->conference_id)->where('union_id', $actor->union_id)->exists(),
            'gereja' => $target->church_id !== null
                && Church::whereKey($target->church_id)->where('conference_id', $actor->conference_id)->exists(),
            default => false,
        };
    }

    private function scopeBelongsToActor(User $actor, ?string $level, ?int $scopeId): bool
    {
        return match ($level) {
            // Admin Nasional's own promotions are handled by its dedicated branch in
            // promote() above (via scopeBelongsToAssignedUnions()), never this generic path.
            'uni' => $scopeId !== null,
            'daerah' => $scopeId !== null
                && Conference::whereKey($scopeId)->where('union_id', $actor->union_id)->exists(),
            'gereja' => $scopeId !== null
                && Church::whereKey($scopeId)->where('conference_id', $actor->conference_id)->exists(),
            default => false,
        };
    }

    /**
     * Admin Nasional's own equivalent of scopeBelongsToActor() — anchored against the actor's
     * ASSIGNED SET of Unions (see User::assignedUnions()) rather than a single union_id/
     * conference_id column, since Admin Nasional has neither of those (it's scoped to a set,
     * not a single region — see UserRole::level()'s doc comment).
     */
    private function scopeBelongsToAssignedUnions(User $actor, ?string $level, ?int $scopeId): bool
    {
        if ($scopeId === null) {
            return false;
        }

        $unionIds = $actor->assignedUnionIds();

        return match ($level) {
            'uni' => Union::whereKey($scopeId)->whereIn('id', $unionIds)->exists(),
            'daerah' => Conference::whereKey($scopeId)->whereIn('union_id', $unionIds)->exists(),
            'gereja' => Church::whereKey($scopeId)->whereHas(
                'conference', fn ($q) => $q->whereIn('union_id', $unionIds)
            )->exists(),
            default => false,
        };
    }

    /**
     * Superadmin/Admin Global have no union_id/conference_id of their own to anchor
     * scopeBelongsToActor's checks against, so bootstrapping just needs the target org-unit
     * to exist at all. 'global' itself needs no org-unit — it's the unrestricted tier.
     */
    private function scopeExists(?string $level, ?int $scopeId): bool
    {
        return match ($level) {
            'global' => true,
            'uni' => $scopeId !== null && Union::whereKey($scopeId)->exists(),
            'daerah' => $scopeId !== null && Conference::whereKey($scopeId)->exists(),
            'gereja' => $scopeId !== null && Church::whereKey($scopeId)->exists(),
            'institusi' => $scopeId !== null && Institution::whereKey($scopeId)->exists(),
            default => false,
        };
    }

    /** Every id in $unionIds must be a real, active Union — used when bootstrapping a new Admin/Pimpinan Nasional. */
    private function unionIdsExist(?array $unionIds): bool
    {
        if (empty($unionIds)) {
            return false;
        }

        $unionIds = array_unique($unionIds);

        return Union::whereIn('id', $unionIds)->count() === count($unionIds);
    }
}
