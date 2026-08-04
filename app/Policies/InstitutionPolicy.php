<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

class InstitutionPolicy
{
    /**
     * Widened beyond Institution::scopeVisibleTo() for two viewer types the Institusi analytics
     * tab opens up to, mirroring ChurchPolicy::view()'s exact same reasoning: a plain member
     * (role === null) sees every institution — Analytics is meant to inform everyone, they have
     * no region — and a gereja-level admin sees their whole Daerah/Konferens's institutions
     * instead of none at all (scopeVisibleTo() has no gereja-level branch, since Institution
     * management never delegates to gereja-level — see UserRole::level()'s doc comment). Every
     * other role (uni/daerah/institusi/nasional) falls straight through to
     * Institution::scopeVisibleTo(), which is strictly region-scoped — no nasional-institution
     * carve-out for them, per the user's explicit call. Mirrors
     * BuildsLeaderboards::analyticsInstitutionScope() exactly.
     */
    public function view(User $user, Institution $institution): bool
    {
        if ($user->role === null) {
            return true;
        }

        if ($user->role->level() === 'gereja') {
            $conferenceId = $user->church?->conference_id;
            $unionId = $user->church?->conference?->union_id;

            return Institution::whereKey($institution->id)
                ->where(function ($q) use ($conferenceId, $unionId) {
                    $q->where(fn ($q2) => $q2->whereNull('union_id')->whereNull('conference_id'))
                        ->when($unionId, fn ($q2) => $q2->orWhere(
                            fn ($q3) => $q3->where('union_id', $unionId)->whereNull('conference_id')
                        ))
                        ->when($conferenceId, fn ($q2) => $q2->orWhere('conference_id', $conferenceId));
                })
                ->exists();
        }

        return Institution::whereKey($institution->id)->visibleTo($user)->exists();
    }

    /**
     * Editing requires actually owning the institution's specific scope, not just having read
     * visibility into it — for a plain member or gereja-level admin, view() is widened beyond
     * scopeVisibleTo() (see above), but neither ever reaches update() at all (isReadOnly()/no
     * role short-circuits first). A nasional (unscoped) institution stays nasional-only to edit;
     * admin_uni may edit anything under their own union (both Uni-level and any Daerah-level
     * institution nested under it, since conference_id always implies union_id — see
     * Institution::scopeVisibleTo()); admin_daerah only their own Daerah's; admin_institusi only
     * the one institution they're bound to.
     */
    public function update(User $user, Institution $institution): bool
    {
        if ($user->role === null || $user->role->isReadOnly() || ! $this->view($user, $institution)) {
            return false;
        }

        return match (true) {
            $user->role->hasNasionalAccess() || $user->role->level() === 'nasional' => true,
            $user->role->level() === 'uni' => $institution->union_id === $user->union_id,
            $user->role->level() === 'daerah' => $institution->conference_id === $user->conference_id,
            $user->role->level() === 'institusi' => $institution->id === $user->institution_id,
            default => false,
        };
    }

    public function delete(User $user, Institution $institution): bool
    {
        return $this->update($user, $institution);
    }

    /**
     * Narrower than view() above, mirroring ChurchPolicy::export(): institusi-level can now
     * *view* their whole Uni/Daerah context for the Analytics tab, but per the same reasoning
     * may only ever export their own single institution's data. gereja-level is widened the
     * same way for view() (see above) but owns no institution at all, so it's denied export
     * outright here rather than falling through to view() — same "view broadly, export
     * narrowly" split already enforced via the browse-directory-analytics gate excluding them
     * from every aggregate institution export. Everyone else exports whatever they can already
     * view, unchanged.
     */
    public function export(User $user, Institution $institution): bool
    {
        if ($user->role?->level() === 'institusi') {
            return $institution->id === $user->institution_id;
        }

        if ($user->role?->level() === 'gereja') {
            return false;
        }

        return $this->view($user, $institution);
    }

    /**
     * Institutions can sit under a Uni, further under a Daerah within it, or under neither (a
     * nasional institution) — per the user's explicit call, so unlike Union (top of its own
     * chain) any non-read-only, non-gereja/institusi role may create one. An admin_institusi is
     * bound to a single existing institution, not authorized to add new ones (same reasoning as
     * ChurchPolicy::create() for gereja-level); gereja-level has no business with Institution
     * at all.
     */
    public function create(User $user): bool
    {
        return $user->role !== null
            && ! $user->role->isReadOnly()
            && ! in_array($user->role->level(), ['gereja', 'institusi'], true);
    }
}
