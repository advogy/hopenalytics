<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

class ChurchPolicy
{
    /**
     * Viewing only requires visibility, not write access — Pimpinan (read-only) can still
     * view every church within their scope, they just can't create/update/delete.
     */
    public function view(User $user, Church $church): bool
    {
        return Church::whereKey($church->id)->visibleTo($user)->exists();
    }

    public function update(User $user, Church $church): bool
    {
        return $user->role !== null && ! $user->role->isReadOnly() && $this->view($user, $church);
    }

    public function delete(User $user, Church $church): bool
    {
        return $this->update($user, $church);
    }

    /**
     * A church is always the leaf (gereja) level, so only roles above gereja may create one —
     * an admin_gereja is bound to a single existing church, not authorized to add new ones.
     */
    public function create(User $user): bool
    {
        return $user->role !== null
            && ! $user->role->isReadOnly()
            && $user->role->level() !== 'gereja';
    }
}
