<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;

class DivisionPolicy
{
    /**
     * Viewing only requires visibility — a uni-level admin can still see their own parent
     * Divisi for context in Kelola Akun, even though they can't edit it (see update()).
     */
    public function view(User $user, Division $division): bool
    {
        return Division::whereKey($division->id)->visibleTo($user)->exists();
    }

    /**
     * Narrower than view(): uni-level (and below) can *see* their parent Divisi but not edit
     * it — same "seeing a level above your own isn't managing your own region" reasoning as
     * UnionPolicy::update(). Combined with view()'s visibleTo() scoping, this leaves only
     * nasional-level and the division's own admin_divisi.
     */
    public function update(User $user, Division $division): bool
    {
        return $user->role !== null
            && ! $user->role->isReadOnly()
            && ! in_array($user->role->level(), ['uni', 'daerah', 'gereja', 'institusi'], true)
            && $this->view($user, $division);
    }

    public function delete(User $user, Division $division): bool
    {
        return $this->update($user, $division);
    }

    /**
     * Divisi is now the top of the chain (mirrors UnionPolicy::create()'s old reasoning when
     * Uni was the top tier), so only unrestricted (global) actors create one. A scoped Admin
     * Nasional's mandate is specifically managing a fixed, pre-existing SET of Union records
     * (see UserRole::level() docblock) — that mandate is about Unions, not about minting new
     * Divisions, so it stays excluded here exactly as it was excluded from creating Unions.
     */
    public function create(User $user): bool
    {
        return $user->role?->hasGlobalAccess() ?? false;
    }
}
