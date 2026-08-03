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
     * Whether $actor may assign $newRole (with the given org-unit $scopeId) to $target.
     * Promotion is delegated by level (decision #5): an actor can only promote into the one
     * level directly below their own, and only within their own org unit — with exceptions:
     * superadmin AND admin_nasional may both bootstrap any level directly (uni/daerah/gereja,
     * no chain needed to seed the first one), except admin_nasional may never assign into
     * nasional itself — that stays superadmin-exclusive. Institusi sits outside the chain
     * entirely (see UserRole::level()) — any nasional-level actor may assign it directly.
     */
    public function promote(User $actor, User $target, UserRole $newRole, ?int $scopeId): bool
    {
        if ($actor->role === null || $actor->role->isReadOnly()) {
            return false;
        }

        if ($newRole->level() === 'institusi') {
            return $actor->role->hasNasionalAccess() && $this->scopeExists('institusi', $scopeId);
        }

        if ($actor->role === UserRole::SuperAdmin) {
            return $this->scopeExists($newRole->level(), $scopeId);
        }

        if ($actor->role === UserRole::AdminNasional) {
            if ($newRole->level() === 'nasional') {
                return false;
            }

            return $this->scopeExists($newRole->level(), $scopeId);
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

        if ($actor->role === UserRole::AdminNasional) {
            // Same bootstrap capability as promote() above — any level except nasional
            // itself, so admin_nasional can never touch superadmin or a peer admin_nasional/
            // pimpinan_nasional account.
            $targetIsNasional = $target->role === UserRole::SuperAdmin
                || ($target->role !== null && $target->role->level() === 'nasional');

            return ! $targetIsNasional;
        }

        if ($target->role === null) {
            // Unassigned members aren't scoped to any org unit yet, so anyone who can
            // promote at all may manage them (mirrors the unassigned list itself, which
            // isn't filtered by region either).
            return $actor->role->promotesToLevel() !== null;
        }

        if ($target->role->level() === 'institusi') {
            return $actor->role->hasNasionalAccess();
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
            'nasional' => true, // superadmin promoting to admin_nasional/pimpinan_nasional: no further scope
            'uni' => $scopeId !== null, // admin_nasional may assign any Union
            'daerah' => $scopeId !== null
                && Conference::whereKey($scopeId)->where('union_id', $actor->union_id)->exists(),
            'gereja' => $scopeId !== null
                && Church::whereKey($scopeId)->where('conference_id', $actor->conference_id)->exists(),
            default => false,
        };
    }

    /**
     * Superadmin/admin_nasional have no union_id/conference_id of their own to anchor
     * scopeBelongsToActor's checks against, so bootstrapping just needs the target org-unit
     * to exist at all.
     */
    private function scopeExists(?string $level, ?int $scopeId): bool
    {
        return match ($level) {
            'nasional' => true,
            'uni' => $scopeId !== null && Union::whereKey($scopeId)->exists(),
            'daerah' => $scopeId !== null && Conference::whereKey($scopeId)->exists(),
            'gereja' => $scopeId !== null && Church::whereKey($scopeId)->exists(),
            'institusi' => $scopeId !== null && Institution::whereKey($scopeId)->exists(),
            default => false,
        };
    }
}
