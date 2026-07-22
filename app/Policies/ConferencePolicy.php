<?php

namespace App\Policies;

use App\Models\Conference;
use App\Models\User;

class ConferencePolicy
{
    /**
     * Viewing only requires visibility — Pimpinan (read-only) can still view every conference
     * within their scope, they just can't create/update/delete.
     */
    public function view(User $user, Conference $conference): bool
    {
        return Conference::whereKey($conference->id)->visibleTo($user)->exists();
    }

    /**
     * Unlike Union, a daerah-level admin managing their OWN conference is exactly "managing
     * your own region" — no level exclusion needed beyond gereja/institusi (which have no
     * business with Conference at all) and visibleTo()'s existing scoping.
     */
    public function update(User $user, Conference $conference): bool
    {
        return $user->role !== null
            && ! $user->role->isReadOnly()
            && ! in_array($user->role->level(), ['gereja', 'institusi'], true)
            && $this->view($user, $conference);
    }

    public function delete(User $user, Conference $conference): bool
    {
        return $this->update($user, $conference);
    }

    /**
     * A Daerah's parent is always a Uni, so nasional-level and admin_uni (creating one under
     * their own union) may create one — an admin_daerah is bound to a single existing
     * conference, not authorized to add new ones.
     */
    public function create(User $user): bool
    {
        return $user->role !== null
            && ! $user->role->isReadOnly()
            && ! in_array($user->role->level(), ['daerah', 'gereja', 'institusi'], true);
    }
}
