<?php

namespace App\Policies;

use App\Models\Union;
use App\Models\User;

class UnionPolicy
{
    /**
     * Viewing only requires visibility — a daerah-level admin can still see their own parent
     * Uni for context in Kelola Akun, even though they can't edit it (see update()).
     */
    public function view(User $user, Union $union): bool
    {
        return Union::whereKey($union->id)->visibleTo($user)->exists();
    }

    /**
     * Narrower than view(): daerah-level (and below) can *see* their parent Uni but not edit
     * it — that's a level above "managing your own region". Combined with view()'s visibleTo()
     * scoping, this leaves only nasional-level and the union's own admin_uni.
     */
    public function update(User $user, Union $union): bool
    {
        return $user->role !== null
            && ! $user->role->isReadOnly()
            && ! in_array($user->role->level(), ['daerah', 'gereja', 'institusi'], true)
            && $this->view($user, $union);
    }

    public function delete(User $user, Union $union): bool
    {
        return $this->update($user, $union);
    }

    /**
     * A Uni is the top of the chain, so only nasional-level actors create one — an admin_uni
     * is bound to a single existing union, not authorized to add new ones (same reasoning as
     * ChurchPolicy::create() for gereja-level).
     */
    public function create(User $user): bool
    {
        return $user->role?->hasNasionalAccess() ?? false;
    }
}
