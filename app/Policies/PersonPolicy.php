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
     *
     * Broader than update()/delete() below, on purpose: ANY plain member (not just their own)
     * and a gereja-level admin's whole Daerah/Konferens both pass here too, since Analytics now
     * links a person's name to this page for exactly that wider, read-only audience
     * (BuildsLeaderboards::analyticsPersonScope()) — write access stays narrow, unaffected,
     * since update()/delete() re-check visibleTo() directly rather than calling this method.
     */
    public function view(User $user, Person $person): bool
    {
        if ($person->user_id === $user->id) {
            return true;
        }

        if ($user->role === null) {
            return true;
        }

        if ($user->role->level() === 'gereja') {
            return $person->conference_id === $user->church?->conference_id;
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
     * Same reasoning as ChurchPolicy::export() — a gereja-level admin can now *view* any
     * person in their whole Daerah/Konferens for context, but per the user's explicit call
     * ("admin gereja hanya bisa export data gerejanya saja") that doesn't extend to exporting
     * someone else's personal data — only their own linked Person, same as everyone else's
     * self-ownership carve-out above.
     */
    public function export(User $user, Person $person): bool
    {
        if ($person->user_id === $user->id) {
            return true;
        }

        if ($user->role?->level() === 'gereja') {
            return false;
        }

        return $this->view($user, $person);
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
