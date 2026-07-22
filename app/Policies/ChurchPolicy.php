<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

class ChurchPolicy
{
    /**
     * Broader than update() below, on purpose: a plain member sees every church (they have no
     * region — Analytics is meant to inform everyone, per the user's explicit call) and a
     * gereja-level admin sees their whole Daerah/Konferens (same breadth as an admin_daerah),
     * not just their own single church — since Analytics now links a church's name to this
     * page for exactly that wider audience (BuildsLeaderboards::analyticsChurchScope()). Write
     * access stays narrow — see update() below, which deliberately does NOT call this method.
     */
    public function view(User $user, Church $church): bool
    {
        if ($user->role === null) {
            return true;
        }

        if ($user->role->level() === 'gereja') {
            return $church->conference_id === $user->church?->conference_id;
        }

        return Church::whereKey($church->id)->visibleTo($user)->exists();
    }

    /**
     * Deliberately re-checks visibleTo() directly instead of calling view() — a gereja-level
     * admin can now *view* their whole conference's churches (see view() above) but must still
     * only ever *edit* their own single church, same as how a daerah-level admin can view their
     * parent Uni without being able to edit it (see UnionPolicy::update()).
     */
    public function update(User $user, Church $church): bool
    {
        return $user->role !== null
            && ! $user->role->isReadOnly()
            && Church::whereKey($church->id)->visibleTo($user)->exists();
    }

    public function delete(User $user, Church $church): bool
    {
        return $this->update($user, $church);
    }

    /**
     * Exporting is a data-extraction action, so it's narrower than view() for gereja-level
     * specifically: they can now *view* their whole Daerah/Konferens for context, but per the
     * user's explicit call may only ever export their own single church's data — everyone else
     * exports whatever they can already view, unchanged.
     */
    public function export(User $user, Church $church): bool
    {
        if ($user->role?->level() === 'gereja') {
            return $church->id === $user->church_id;
        }

        return $this->view($user, $church);
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
