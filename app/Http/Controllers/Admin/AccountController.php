<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Union;
use App\Models\User;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use BuildsLeaderboards;

    /**
     * "Kelola Akun" — one page, five tabs (Uni / Daerah / Gereja / Institusi / Personal), same
     * tab pattern as the account directory (resources/views/partials/tab-script.blade.php),
     * instead of separate admin pages for what is really one "manage the accounts I'm
     * responsible for" concern. Institusi isn't nested under Uni/Daerah (see UserRole::level()),
     * so it has no parent-count columns the way Daerah/Gereja do. Personal used to be its own
     * page (PersonController::index(), now removed) — moved here verbatim, including its
     * region-scoped visibleTo() and its name-or-city search (unlike the other four tabs, which
     * only search by name). Each tab paginates & searches independently (distinct query-string
     * page names) since all five render at once and are just toggled client-side.
     */
    public function index(Request $request)
    {
        $visibleTabs = $this->visibleTabsFor($request->user());
        $requestedTab = $request->query('tab');
        $activeTab = (is_string($requestedTab) && ($visibleTabs[$requestedTab] ?? false))
            ? $requestedTab
            : array_key_first(array_filter($visibleTabs));

        $searchUni = trim((string) $request->query('search_uni'));
        $searchDaerah = trim((string) $request->query('search_daerah'));
        $searchGereja = trim((string) $request->query('search_gereja'));
        $searchInstitusi = trim((string) $request->query('search_institusi'));
        $searchPersonal = trim((string) $request->query('search_personal'));

        // Tab switching is client-side only (no page reload — see partials/tab-script.blade.php),
        // so a pagination link built from the *current* request's query string would still
        // carry whatever tab was active on page load, not whatever tab the visitor is actually
        // looking at when they click it. appends(['tab' => ...]) forces each paginator's own
        // links to always point back to its own tab, regardless of what was in the URL when
        // the page was first rendered.
        $unions = Union::withCount(['conferences', 'people', 'users'])
            ->visibleTo($request->user())
            ->when($searchUni, fn ($q) => $q->where('name', 'like', "%{$searchUni}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'uni_page')
            ->withQueryString()
            ->appends(['tab' => 'uni']);

        $conferences = Conference::with('union')->withCount(['churches', 'people', 'users'])
            ->visibleTo($request->user())
            ->when($searchDaerah, fn ($q) => $q->where('name', 'like', "%{$searchDaerah}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'daerah_page')
            ->withQueryString()
            ->appends(['tab' => 'daerah']);

        // No is_active filter here (unlike the rest of the app's church queries): this is the
        // management view, so a deactivated church still needs to show up — otherwise there'd
        // be no way to find it again and toggle it back on.
        $churches = Church::with('conference.union')->withCount('users')
            ->visibleTo($request->user())
            ->when($searchGereja, fn ($q) => $q->where('name', 'like', "%{$searchGereja}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'gereja_page')
            ->withQueryString()
            ->appends(['tab' => 'gereja']);

        $institutions = Institution::with('conference.union', 'union')->withCount('users')
            ->manageableBy($request->user())
            ->when($searchInstitusi, fn ($q) => $q->where('name', 'like', "%{$searchInstitusi}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'institusi_page')
            ->withQueryString()
            ->appends(['tab' => 'institusi']);

        // Ported verbatim from the old PersonController::index() — region-scoped like the
        // other four tabs (unlike the public directory), and searches name OR city (the other
        // tabs only search name; Person's search kept its own broader shape on the move).
        $people = Person::with(['union', 'conference.union', 'user:id,name'])
            ->withCount('socials')
            ->visibleTo($request->user())
            ->when($searchPersonal, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$searchPersonal}%")
                ->orWhere('city', 'like', "%{$searchPersonal}%"),
            ))
            ->orderBy('name')
            ->paginate(20, ['*'], 'personal_page')
            ->withQueryString()
            ->appends(['tab' => 'personal']);

        $accountsNeedingAttention = $this->accountsNeedingAttentionQuery()->count();

        // Active entities the current user manages that have never had a single social account
        // added — summed across the three entity types for the stat card; the detail page
        // (noSocials() below) reuses the same three query builders to list them out.
        $entitiesWithoutSocials = $this->churchesWithoutSocialsQuery($request->user())->count()
            + $this->institutionsWithoutSocialsQuery($request->user())->count()
            + $this->peopleWithoutSocialsQuery($request->user())->count();

        return view('admin.accounts.index', [
            'activeTab' => $activeTab,
            'accountsNeedingAttention' => $accountsNeedingAttention,
            'entitiesWithoutSocials' => $entitiesWithoutSocials,
            'unions' => $unions,
            'conferences' => $conferences,
            'churches' => $churches,
            'institutions' => $institutions,
            'people' => $people,
            'searchUni' => $searchUni,
            'searchDaerah' => $searchDaerah,
            'searchGereja' => $searchGereja,
            'searchInstitusi' => $searchInstitusi,
            'searchPersonal' => $searchPersonal,
            'visibleTabs' => $visibleTabs,
        ]);
    }

    /**
     * Detail page for the "Belum punya akun sosial" stat card — same three entity types as the
     * tabs above, grouped into their own section instead of one mixed table, since each type
     * has a different natural "add socials" destination (view/edit route) and different extra
     * columns worth showing (Gereja/Personal show Daerah, Institusi shows Uni/Daerah or Nasional).
     */
    public function noSocials(Request $request)
    {
        $user = $request->user();

        $churches = $this->churchesWithoutSocialsQuery($user)->with('conference.union')->orderBy('name')->get();
        $institutions = $this->institutionsWithoutSocialsQuery($user)->with('conference.union', 'union')->orderBy('name')->get();
        $people = $this->peopleWithoutSocialsQuery($user)->with(['union', 'conference.union'])->orderBy('name')->get();

        return view('admin.accounts.no-socials', [
            'churches' => $churches,
            'institutions' => $institutions,
            'people' => $people,
        ]);
    }

    /** Active churches the user manages with zero social accounts ever added. */
    private function churchesWithoutSocialsQuery(User $user)
    {
        return Church::where('is_active', true)->visibleTo($user)->doesntHave('socials');
    }

    /** Same as churchesWithoutSocialsQuery(), for Institution instead of Church. */
    private function institutionsWithoutSocialsQuery(User $user)
    {
        return Institution::where('is_active', true)->manageableBy($user)->doesntHave('socials');
    }

    /** Same as churchesWithoutSocialsQuery(), for Person instead of Church. */
    private function peopleWithoutSocialsQuery(User $user)
    {
        return Person::where('is_active', true)->visibleTo($user)->doesntHave('socials');
    }

    /**
     * Which tabs a visitor sees, per the user's explicit call: each level only manages what's
     * at or below "their own region", never a level above it or their own entity type (editing
     * your own Uni/Daerah's identity isn't part of "managing your region" here — data within it
     * is). admin_uni therefore skips Uni; admin_daerah skips Uni AND Daerah; admin_institusi
     * (not chained under Uni/Daerah/Gereja at all — see UserRole::level()) only ever sees
     * Institusi. admin_gereja sees no organization tab at all — their one church is reached via
     * the "Gereja Saya" nav link instead (see layouts.app) — but does reach this page now for
     * Personal, which was never gereja-excluded (see manage-people). Personal is therefore
     * always true here: this method only ever runs after the manage-people route gate already
     * passed, so every caller is guaranteed non-null, non-read-only. Nasional-level sees
     * everything. Which rows show up within a visible tab is a separate concern, handled by
     * visibleTo()/manageableBy() on each query above.
     */
    private function visibleTabsFor(User $user): array
    {
        $level = ($user->role?->hasNasionalAccess() ?? false) ? 'nasional' : $user->role?->level();

        return match ($level) {
            'nasional' => ['uni' => true, 'daerah' => true, 'gereja' => true, 'institusi' => true, 'personal' => true],
            'uni' => ['uni' => false, 'daerah' => true, 'gereja' => true, 'institusi' => true, 'personal' => true],
            'daerah' => ['uni' => false, 'daerah' => false, 'gereja' => true, 'institusi' => true, 'personal' => true],
            'institusi' => ['uni' => false, 'daerah' => false, 'gereja' => false, 'institusi' => true, 'personal' => true],
            default => ['uni' => false, 'daerah' => false, 'gereja' => false, 'institusi' => false, 'personal' => true],
        };
    }
}
