<?php

namespace App\Policies;

use App\Models\ChurchSocial;
use App\Models\User;

class ChurchSocialPolicy
{
    /**
     * A member (role === null) always passes for their own linked person's social accounts —
     * same self-ownership carve-out as PersonPolicy.
     */
    private function ownedByMember(User $user, ChurchSocial $social): bool
    {
        return $social->person_id !== null && $social->person?->user_id === $user->id;
    }

    public function view(User $user, ChurchSocial $social): bool
    {
        if ($this->ownedByMember($user, $social)) {
            return true;
        }

        return ChurchSocial::whereKey($social->id)->visibleTo($user)->exists();
    }

    public function update(User $user, ChurchSocial $social): bool
    {
        if ($this->ownedByMember($user, $social)) {
            return true;
        }

        return $user->role !== null && ! $user->role->isReadOnly()
            && ChurchSocial::whereKey($social->id)->visibleTo($user)->exists();
    }
}
