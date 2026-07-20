<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    /**
     * A self-registered member (role === null) always passes for their own linked Person —
     * that's their one and only permitted page (decision #4) — regardless of scope, which
     * would otherwise hide an independent/unassigned Person from everyone but nasional roles.
     */
    public function view(User $user, Person $person): bool
    {
        if ($person->user_id === $user->id) {
            return true;
        }

        return Person::whereKey($person->id)->visibleTo($user)->exists();
    }

    /**
     * Same self-ownership carve-out as view() — a member manages their own social accounts
     * (add/edit handles, manual stat entry) even though they hold no role.
     */
    public function update(User $user, Person $person): bool
    {
        if ($person->user_id === $user->id) {
            return true;
        }

        return $user->role !== null && ! $user->role->isReadOnly()
            && Person::whereKey($person->id)->visibleTo($user)->exists();
    }

    public function delete(User $user, Person $person): bool
    {
        // Deactivating someone else's account is an admin action; a member never deletes
        // their own record (there's nothing else for them to do afterward but re-register).
        return $person->user_id !== $user->id
            && $user->role !== null && ! $user->role->isReadOnly()
            && Person::whereKey($person->id)->visibleTo($user)->exists();
    }

    /**
     * People aren't bound to a single leaf level (they can be independent, decision #2),
     * so any assigned, non-read-only role may add one — narrower than "level above gereja"
     * since Person isn't church-scoped at all.
     */
    public function create(User $user): bool
    {
        return $user->role !== null && ! $user->role->isReadOnly();
    }
}
