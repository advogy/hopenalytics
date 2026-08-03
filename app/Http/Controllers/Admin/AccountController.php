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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $sortUni = $request->query('sort_uni', 'name_asc');
        $sortDaerah = $request->query('sort_daerah', 'name_asc');
        $sortGereja = $request->query('sort_gereja', 'name_asc');
        $sortInstitusi = $request->query('sort_institusi', 'name_asc');
        $sortPersonal = $request->query('sort_personal', 'name_asc');

        // Region filter — one per tab (Uni gets none, it's the top tier already), since every
        // tab here is already independently searched/sorted/paginated; the shared region-filter
        // partial's field-name override (see its own doc comment) keeps each tab's own
        // union_id/conference_id from colliding when several of them sit on the same page.
        // regionFilterOptions() (from BuildsLeaderboards, shared with Analitik & Grafik etc.)
        // scopes the pickers themselves to what the viewer may pick from — unlike the public,
        // unscoped Direktori Akun, this management page should only ever offer the viewer's own
        // region.
        $selectedUnionIdDaerah = $request->query('union_id_daerah');
        $selectedUnionIdGereja = $request->query('union_id_gereja');
        $selectedConferenceIdGereja = $request->query('conference_id_gereja');
        $selectedUnionIdInstitusi = $request->query('union_id_institusi');
        $selectedConferenceIdInstitusi = $request->query('conference_id_institusi');
        $selectedUnionIdPersonal = $request->query('union_id_personal');
        $selectedConferenceIdPersonal = $request->query('conference_id_personal');

        [$unionOptionsDaerah] = $this->regionFilterOptions(null);
        [$unionOptionsGereja, $conferenceOptionsGereja] = $this->regionFilterOptions($selectedUnionIdGereja);
        [$unionOptionsInstitusi, $conferenceOptionsInstitusi] = $this->regionFilterOptions($selectedUnionIdInstitusi);
        [$unionOptionsPersonal, $conferenceOptionsPersonal] = $this->regionFilterOptions($selectedUnionIdPersonal);

        // Church has no union_id column of its own — only conference_id — hence the
        // whereHas('conference') branch for a union-only (no conference chosen) filter. Same
        // shape as ChurchDashboardController::directory()'s equivalents.
        $applyChurchRegionFilter = fn ($query) => $query
            ->when($selectedConferenceIdGereja, fn ($q) => $q->where('conference_id', $selectedConferenceIdGereja))
            ->when($selectedUnionIdGereja && ! $selectedConferenceIdGereja, fn ($q) => $q->whereHas(
                'conference', fn ($q2) => $q2->where('union_id', $selectedUnionIdGereja)
            ));

        $applyInstitutionRegionFilter = fn ($query) => $query
            ->when($selectedConferenceIdInstitusi, fn ($q) => $q->where('conference_id', $selectedConferenceIdInstitusi))
            ->when($selectedUnionIdInstitusi && ! $selectedConferenceIdInstitusi, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('union_id', $selectedUnionIdInstitusi)
                ->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $selectedUnionIdInstitusi))
            ));

        $applyPersonRegionFilter = fn ($query) => $query
            ->when($selectedConferenceIdPersonal, fn ($q) => $q->where('conference_id', $selectedConferenceIdPersonal))
            ->when($selectedUnionIdPersonal && ! $selectedConferenceIdPersonal, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('union_id', $selectedUnionIdPersonal)
                ->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $selectedUnionIdPersonal))
            ));

        // Tab switching is client-side only (no page reload — see partials/tab-script.blade.php),
        // so a pagination link built from the *current* request's query string would still
        // carry whatever tab was active on page load, not whatever tab the visitor is actually
        // looking at when they click it. appends(['tab' => ...]) forces each paginator's own
        // links to always point back to its own tab, regardless of what was in the URL when
        // the page was first rendered.
        $unions = Union::withCount(['conferences', 'people', 'users'])
            ->visibleTo($request->user())
            ->when($searchUni, fn ($q) => $q->where('name', 'like', "%{$searchUni}%"))
            ->tap(fn ($q) => $this->applyNameOrStatusSort($q, $sortUni))
            ->paginate(30, ['*'], 'uni_page')
            ->withQueryString()
            ->appends(['tab' => 'uni']);

        $conferences = Conference::with('union')->withCount(['churches', 'people', 'users'])
            ->visibleTo($request->user())
            ->when($searchDaerah, fn ($q) => $q->where('name', 'like', "%{$searchDaerah}%"))
            ->when($selectedUnionIdDaerah, fn ($q) => $q->where('union_id', $selectedUnionIdDaerah))
            ->tap(fn ($q) => match ($sortDaerah) {
                'name_desc' => $q->orderByDesc('name'),
                'union_asc' => $q->orderBy(Union::select('name')->whereColumn('unions.id', 'conferences.union_id'))->orderBy('name'),
                'union_desc' => $q->orderByDesc(Union::select('name')->whereColumn('unions.id', 'conferences.union_id'))->orderBy('name'),
                'status_active' => $q->orderByDesc('is_active')->orderBy('name'),
                'status_inactive' => $q->orderBy('is_active')->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->paginate(30, ['*'], 'daerah_page')
            ->withQueryString()
            ->appends(['tab' => 'daerah']);

        // No is_active filter here (unlike the rest of the app's church queries): this is the
        // management view, so a deactivated church still needs to show up — otherwise there'd
        // be no way to find it again and toggle it back on.
        $churches = Church::with('conference.union')->withCount('users')
            ->visibleTo($request->user())
            ->when($searchGereja, fn ($q) => $q->where('name', 'like', "%{$searchGereja}%"))
            ->tap($applyChurchRegionFilter)
            ->tap(fn ($q) => match ($sortGereja) {
                'name_desc' => $q->orderByDesc('name'),
                'city_asc' => $q->orderBy('city')->orderBy('name'),
                'city_desc' => $q->orderByDesc('city')->orderBy('name'),
                'daerah_asc' => $q->orderBy(Conference::select('name')->whereColumn('conferences.id', 'churches.conference_id'))->orderBy('name'),
                'daerah_desc' => $q->orderByDesc(Conference::select('name')->whereColumn('conferences.id', 'churches.conference_id'))->orderBy('name'),
                'status_active' => $q->orderByDesc('is_active')->orderBy('name'),
                'status_inactive' => $q->orderBy('is_active')->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->paginate(30, ['*'], 'gereja_page')
            ->withQueryString()
            ->appends(['tab' => 'gereja']);

        $institutions = Institution::with('conference.union', 'union')->withCount('users')
            ->manageableBy($request->user())
            ->when($searchInstitusi, fn ($q) => $q->where('name', 'like', "%{$searchInstitusi}%"))
            ->tap($applyInstitutionRegionFilter)
            ->tap(fn ($q) => match ($sortInstitusi) {
                'name_desc' => $q->orderByDesc('name'),
                'region_asc' => $q->orderBy($this->institutionRegionOrderExpression())->orderBy('name'),
                'region_desc' => $q->orderByDesc($this->institutionRegionOrderExpression())->orderBy('name'),
                'status_active' => $q->orderByDesc('is_active')->orderBy('name'),
                'status_inactive' => $q->orderBy('is_active')->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->paginate(30, ['*'], 'institusi_page')
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
            ->tap($applyPersonRegionFilter)
            ->tap(fn ($q) => match ($sortPersonal) {
                'name_desc' => $q->orderByDesc('name'),
                'city_asc' => $q->orderBy('city')->orderBy('name'),
                'city_desc' => $q->orderByDesc('city')->orderBy('name'),
                'scope_asc' => $q->orderBy($this->personScopeOrderExpression())->orderBy('name'),
                'scope_desc' => $q->orderByDesc($this->personScopeOrderExpression())->orderBy('name'),
                'status_active' => $q->orderByDesc('is_active')->orderBy('name'),
                'status_inactive' => $q->orderBy('is_active')->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->paginate(30, ['*'], 'personal_page')
            ->withQueryString()
            ->appends(['tab' => 'personal']);

        $accountsNeedingAttention = $this->accountsNeedingAttentionQuery()->count();
        $autoFetchAccountsCount = $this->autoFetchAccountsQuery()->count();

        // Active entities the current user manages that have never had a single social account
        // added — summed across the three entity types for the stat card; the detail page
        // (noSocials() below) reuses the same three query builders to list them out.
        $entitiesWithoutSocials = $this->churchesWithoutSocialsQuery($request->user())->count()
            + $this->institutionsWithoutSocialsQuery($request->user())->count()
            + $this->peopleWithoutSocialsQuery($request->user())->count();

        return view('admin.accounts.index', [
            'activeTab' => $activeTab,
            'accountsNeedingAttention' => $accountsNeedingAttention,
            'autoFetchAccountsCount' => $autoFetchAccountsCount,
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
            'sortUni' => $sortUni,
            'sortDaerah' => $sortDaerah,
            'sortGereja' => $sortGereja,
            'sortInstitusi' => $sortInstitusi,
            'sortPersonal' => $sortPersonal,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $this->isUniView(),
            'unionOptionsDaerah' => $unionOptionsDaerah,
            'unionOptionsGereja' => $unionOptionsGereja,
            'conferenceOptionsGereja' => $conferenceOptionsGereja,
            'unionOptionsInstitusi' => $unionOptionsInstitusi,
            'conferenceOptionsInstitusi' => $conferenceOptionsInstitusi,
            'unionOptionsPersonal' => $unionOptionsPersonal,
            'conferenceOptionsPersonal' => $conferenceOptionsPersonal,
            'selectedUnionIdDaerah' => $selectedUnionIdDaerah,
            'selectedUnionIdGereja' => $selectedUnionIdGereja,
            'selectedConferenceIdGereja' => $selectedConferenceIdGereja,
            'selectedUnionIdInstitusi' => $selectedUnionIdInstitusi,
            'selectedConferenceIdInstitusi' => $selectedConferenceIdInstitusi,
            'selectedUnionIdPersonal' => $selectedUnionIdPersonal,
            'selectedConferenceIdPersonal' => $selectedConferenceIdPersonal,
            'visibleTabs' => $visibleTabs,
        ]);
    }

    /** Shared by every tab's "name"/"status" sort options — the two every tab has in common. */
    private function applyNameOrStatusSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderByDesc('name'),
            'status_active' => $query->orderByDesc('is_active')->orderBy('name'),
            'status_inactive' => $query->orderBy('is_active')->orderBy('name'),
            default => $query->orderBy('name'),
        };
    }

    /**
     * An institution's "region" column (see admin.accounts.index) shows its Conference name if
     * it has one, else its Union name, else "Nasional" — this mirrors that same fallback chain
     * as a single orderable expression, since Eloquent's orderBy() can't sort by "whichever of
     * these two relations happens to be set" any other way. Institutions/People are the only
     * entities that attach to a Conference OR a Union OR neither (see UserRole::level()'s "institusi
     * sits outside the nasional→uni→daerah chain" — Church always has a Conference, Union/Conference
     * rows sort by their own name column directly), so this pattern isn't needed elsewhere.
     */
    private function institutionRegionOrderExpression()
    {
        return DB::raw('COALESCE(
            (SELECT name FROM conferences WHERE conferences.id = institutions.conference_id),
            (SELECT name FROM unions WHERE unions.id = institutions.union_id)
        )');
    }

    /** Same fallback chain as institutionRegionOrderExpression(), for Person's "scope" column instead. */
    private function personScopeOrderExpression()
    {
        return DB::raw('COALESCE(
            (SELECT name FROM conferences WHERE conferences.id = people.conference_id),
            (SELECT name FROM unions WHERE unions.id = people.union_id)
        )');
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
