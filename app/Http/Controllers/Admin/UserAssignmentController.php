<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSuggestionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Http\Controllers\Concerns\HandlesModalForms;
use App\Http\Controllers\Controller;
use App\Models\AdminSuggestion;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Division;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Union;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UserAssignmentController extends Controller
{
    // Reused only for its Uni/Daerah cascading searchable-select filter (regionFilterOptions())
    // on the "Belum Ada Admin" tab, per the user's explicit call to match Analitik & Grafik's own
    // filter UI exactly rather than a plain <select> — isNasionalView()/isUniView() decide which
    // of the two boxes render, regionFilterOptions() itself still narrows the actual options to
    // whatever this actor's own role can see, same as everywhere else that trait is used.
    use BuildsLeaderboards;

    // edit()/update()'s own modal support — see that trait's own doc comment. Pulled in directly
    // (not via RedirectsToAccountsTab, which this controller has no other use for) since none of
    // its Kelola-Akun tab-redirect logic applies here.
    use HandlesModalForms;

    /**
     * A scoped "manage people under me" page — the level an actor may promote into is
     * always exactly one below their own (decision #5), so the whole page is built around
     * that single target level. Institusi is the one exception: it sits outside that chain
     * (see UserRole::level()), so its roles are merged into the same role/scope dropdown for
     * any nasional-level actor rather than getting a separate assignment flow. Superadmin AND
     * admin_nasional get a further exception (bootstrap capability): they may assign/review
     * uni/daerah/gereja directly, since no Admin Uni/Daerah needs to exist yet to seed one —
     * admin_nasional just can't bootstrap into nasional itself (see UserPolicy::promote()).
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        $targetLevel = $actor->role?->promotesToLevel();
        $isSuperAdmin = $actor->role === UserRole::SuperAdmin;
        $isGlobalAccess = $actor->role?->hasGlobalAccess() ?? false;
        $canManageInstitutions = $isGlobalAccess || $actor->role === UserRole::AdminNasional;
        $canBootstrapAnyLevel = $isGlobalAccess || $actor->role === UserRole::AdminNasional;

        // SuperAdmin alone may bootstrap a new Admin/Pimpinan Global (mirrors the pre-existing
        // "admin_nasional may never touch nasional itself" caution one tier up — see
        // UserPolicy::promote()); Admin Global bootstraps nasional and below; a scoped Admin
        // Nasional bootstraps only within their own assigned Unions (uni/daerah/gereja), same
        // reach as before, just no longer unrestricted.
        $bootstrapLevels = match (true) {
            $isSuperAdmin => ['global', 'nasional', 'divisi', 'uni', 'daerah', 'gereja'],
            $actor->role === UserRole::AdminGlobal => ['nasional', 'divisi', 'uni', 'daerah', 'gereja'],
            default => ['divisi', 'uni', 'daerah', 'gereja'],
        };

        // Only ever non-null for a scoped Admin Nasional — used below to restrict every
        // bootstrap-branch query (scope pickers + existing admin/pimpinan lists) to their own
        // assigned Unions. Stays null for SuperAdmin/Admin Global, whose bootstrap reach is
        // genuinely unrestricted.
        $assignedUnionIds = $actor->role === UserRole::AdminNasional ? $actor->assignedUnionIds() : null;

        abort_if($targetLevel === null, 403);

        // "Semua User" tab's own filter/sort state — kept the all_* prefix (mirroring Kelola
        // Akun's own per-tab search_uni/search_daerah/etc. convention) even though this is now
        // the only filterable tab on the page, so a future second tab can't collide with it.
        $allSearch = trim((string) $request->query('all_search'));
        $allSort = in_array($request->query('all_sort'), ['name_asc', 'name_desc', 'date_asc', 'date_desc'], true)
            ? $request->query('all_sort')
            : 'date_desc';
        $allVerification = in_array($request->query('all_verification'), ['pending', 'verified'], true)
            ? $request->query('all_verification')
            : 'all';
        // 'all' | 'unassigned' (role === null) | one of UserRole's own values — validated
        // against every role that exists, not just the ones $allRoleOptions below actually
        // offers, so a stale/manually-edited URL from before a permission change (or a
        // link shared by a more broadly-scoped admin) degrades to "no filter" instead of a
        // silently-wrong empty result.
        $allRoleValues = array_map(fn (UserRole $r) => $r->value, UserRole::cases());
        $allRole = $request->query('all_role') === 'unassigned' || in_array($request->query('all_role'), $allRoleValues, true)
            ? $request->query('all_role')
            : 'all';

        // "Semua User" — every registered user (any role, or none yet), scoped to the same
        // region reach as every other tab on this page rather than left wide open: a global
        // actor (SuperAdmin/Admin Global, or an unrestricted Admin Nasional) sees everyone, a
        // scoped Admin Nasional sees their own assigned Unions' subtree, and an Admin
        // Divisi/Uni/Daerah sees their own subtree — see applyAllUsersScope() for the actual
        // relation chains: a Person-based check for role=null members (who self-report their
        // union/conference via "Lengkapi Profil" rather than having it on their own User row —
        // see CompleteProfileController) and a relation-based check for role-assigned ones
        // combined into one condition there.
        $allUsersBase = User::query()
            // Every row-action here is already excluded for the actor's own row (see
            // row-actions.blade.php's own auth()->user()->is($user) guard) — leaving the row
            // itself in the list only ever showed a "this is you" placeholder where actions
            // would go, so it's dropped outright instead per the user's explicit call.
            ->whereKeyNot($actor->id)
            ->when(! $isGlobalAccess, fn ($q) => $this->applyAllUsersScope($q, $actor, $targetLevel, $assignedUnionIds, $canManageInstitutions));

        $allUsersTotal = (clone $allUsersBase)->count();
        // Same scope as $allUsersTotal above (the actor's own full reach, independent of
        // whatever filter happens to be currently applied) — a row of stat cards next to it, per
        // the user's explicit call.
        $allUsersUnverifiedTotal = (clone $allUsersBase)->whereNull('email_verified_at')->count();
        // The old "Belum Ditugaskan" tab's own headline number, now surfaced as a stat card here
        // instead of its own tab (see the region-scoped query it used to run — role === null +
        // this same scope, still exactly what applyAllUsersScope() covers for these users too).
        $allUsersUnassignedTotal = (clone $allUsersBase)->whereNull('role')->count();

        $allUsers = (clone $allUsersBase)
            // Eager-loaded for the "Peran & Wilayah" column's own $scopeDisplayFor() lookup (see
            // index.blade.php's @php block) — every relation that closure might reach through,
            // matching $trashedUsers' own eager-load list above.
            ->with(['division', 'union.division', 'conference.union', 'church.conference', 'institution', 'assignedUnions'])
            // Also matches the name of whichever region is directly set on the row (division/
            // union/conference/church/institution — never a parent/grandparent of that, e.g. a
            // Gereja admin's own Daerah name isn't searched, just their Gereja's) — per the
            // user's explicit call ("ketik 'Cawang' untuk cari admin gereja itu").
            ->when($allSearch, fn ($q) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$allSearch}%")
                    ->orWhere('email', 'like', "%{$allSearch}%")
                    ->orWhereHas('division', fn ($q3) => $q3->where('name', 'like', "%{$allSearch}%"))
                    ->orWhereHas('union', fn ($q3) => $q3->where('name', 'like', "%{$allSearch}%"))
                    ->orWhereHas('conference', fn ($q3) => $q3->where('name', 'like', "%{$allSearch}%"))
                    ->orWhereHas('church', fn ($q3) => $q3->where('name', 'like', "%{$allSearch}%"))
                    ->orWhereHas('institution', fn ($q3) => $q3->where('name', 'like', "%{$allSearch}%"))
            ))
            ->when($allVerification === 'pending', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($allVerification === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($allRole === 'unassigned', fn ($q) => $q->whereNull('role'))
            ->when($allRole !== 'all' && $allRole !== 'unassigned', fn ($q) => $q->where('role', $allRole))
            ->when($allSort === 'name_asc', fn ($q) => $q->orderBy('name'))
            ->when($allSort === 'name_desc', fn ($q) => $q->orderByDesc('name'))
            ->when($allSort === 'date_asc', fn ($q) => $q->orderBy('created_at'))
            ->when($allSort === 'date_desc', fn ($q) => $q->orderByDesc('created_at'))
            ->paginate(50, ['*'], 'all_page')
            // withQueryString() alone only preserves whatever 'tab' happened to already be in
            // the CURRENT url — which is nothing at all on this tab's own default landing state
            // (it's the fallback, never written into the query string), and can even be a
            // DIFFERENT tab's value if the visitor switched tabs client-side (tab buttons never
            // touch the url) and then clicked this table's own pagination link. Explicitly
            // appending 'tab' after withQueryString() overrides just that one key so this tab's
            // own pager always points back to itself, regardless of what the url said before —
            // confirmed live: without this a page-2 click could land back on whichever tab's
            // query string was last written (e.g. Saran Admin's own).
            ->withQueryString()
            ->appends(['tab' => 'all']);

        $institutionOptions = $canManageInstitutions
            ? Institution::where('is_active', true)->orderBy('name')->get()
            : collect();

        // The role dropdown on "Semua User"'s own "Jadikan Admin / Pimpinan" modal (see
        // index.blade.php's #promote-modal) and the scope options it reveals per role — merged
        // here so institusi is just two more options rather than a separate flow.
        $roles = $canBootstrapAnyLevel
            ? collect($bootstrapLevels)->flatMap(fn ($level) => [$this->adminRoleForLevel($level), $this->pimpinanRoleForLevel($level)])->all()
            : $this->rolesForLevel($targetLevel);

        // "Semua User" tab's own role FILTER options — deliberately not $roles above (which is
        // "what this actor may promote someone INTO", one level below their own — see that
        // variable's own comment) but "every role this actor can actually SEE" in that tab's own
        // list: their own level's admin/pimpinan pair too (applyAllUsersScope() already surfaces
        // peer-level accounts within the actor's own region, e.g. a fellow Admin Uni in the same
        // Divisi — see its 'uni' branch), plus everything below it down to Gereja, per the user's
        // explicit call ("kalau di admin uni, muncul ... uni, daerah, dan seterusnya yg ada
        // dibawahnya"). A canBootstrapAnyLevel actor's reach already spans everything
        // applyAllUsersScope() would ever return for them, so the full role list stays
        // unrestricted for those rather than narrowed to $bootstrapLevels only.
        $allRoleOptions = $canBootstrapAnyLevel
            ? UserRole::cases()
            : collect(['divisi', 'uni', 'daerah', 'gereja'])
                ->skipUntil(fn ($level) => $level === $actor->role?->level())
                ->flatMap(fn ($level) => $this->rolesForLevel($level))
                ->all();

        // "Daftar Admin & Pimpinan" — the merged Admin/Pemimpin/Institusi tabs, per the user's
        // explicit call ("gabung tab Daftar Admin, Daftar Pimpinan, Institusi"). Same filter
        // model as "Semua User" (search + role + sort — see $allRoleOptions above, reused
        // directly here minus SuperAdmin, who was never part of any of the 3 merged tabs to
        // begin with), plus a Uni -> Daerah cascading region filter (regionFilterOptions(),
        // already used the same way by "Belum Ada Admin") since this tab is specifically about
        // regionally-scoped staff — unlike "Semua User", which spans every account including
        // ones with no region at all.
        $staffSearch = trim((string) $request->query('staff_search'));
        // No date_asc/date_desc here (unlike "Semua User"'s own sort) — this tab doesn't show a
        // "Tanggal Daftar" column at all, per the user's explicit call. region_asc/region_desc
        // (see the query below) sorts by whichever Wilayah name each row's own level actually
        // has — one option that works across the mixed levels this list holds, rather than a
        // separate "sort by Uni"/"sort by Daerah"/... option per level, per the user's own pick.
        $staffSort = in_array($request->query('staff_sort'), ['name_asc', 'name_desc', 'region_asc', 'region_desc'], true)
            ? $request->query('staff_sort')
            : 'name_asc';
        $staffRoleOptions = collect($allRoleOptions)->reject(fn (UserRole $r) => $r === UserRole::SuperAdmin)->values();
        $staffRoleValues = $staffRoleOptions->map(fn (UserRole $r) => $r->value)->all();
        $staffRole = in_array($request->query('staff_role'), $staffRoleValues, true) ? $request->query('staff_role') : 'all';
        $staffSelectedUnionId = $request->query('staff_union_id');
        $staffSelectedConferenceId = $request->query('staff_conference_id');
        [$staffUnionOptions, $staffConferenceOptions] = $this->regionFilterOptions($staffSelectedUnionId);
        // Institusi sits outside the Divisi/Uni/Daerah/Gereja tree entirely (no Union tie at all
        // — see index()'s own "Institusi" comment further down), so it gets its own separate
        // filter rather than folding into the Uni/Daerah cascade above; only ever meaningful for
        // a $canManageInstitutions actor, matching every other institution-related gate on this
        // page.
        $staffSelectedInstitutionId = $request->query('staff_institution_id');
        $staffInstitutionOptions = $canManageInstitutions
            ? Institution::where('is_active', true)->orderBy('name')->get()
            : collect();

        // Same region-scoping (applyAllUsersScope()) and self-exclusion as "Semua User" — see
        // that query's own doc comment above — narrowed to role-assigned, non-SuperAdmin
        // accounts only, plus this tab's own union_id/conference_id filter. The union/conference
        // check walks the same relation chains proven correct elsewhere in this controller
        // (union_id directly for a Uni-level account, conference.union_id for a Daerah-level
        // one, church.conference.union_id for a Gereja-level one) rather than a new, unverified
        // shape.
        $staffUsersBase = User::query()
            ->whereNotNull('role')
            ->where('role', '!=', UserRole::SuperAdmin->value)
            ->whereKeyNot($actor->id)
            ->when(! $isGlobalAccess, fn ($q) => $this->applyAllUsersScope($q, $actor, $targetLevel, $assignedUnionIds, $canManageInstitutions))
            ->when($staffRole !== 'all', fn ($q) => $q->where('role', $staffRole))
            ->when($staffSelectedUnionId, fn ($q) => $q->where(
                fn ($q2) => $q2->where('union_id', $staffSelectedUnionId)
                    ->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $staffSelectedUnionId))
                    ->orWhereHas('church.conference', fn ($q3) => $q3->where('union_id', $staffSelectedUnionId))
            ))
            ->when($staffSelectedConferenceId, fn ($q) => $q->where(
                fn ($q2) => $q2->where('conference_id', $staffSelectedConferenceId)
                    ->orWhereHas('church', fn ($q3) => $q3->where('conference_id', $staffSelectedConferenceId))
            ))
            ->when($staffSelectedInstitutionId, fn ($q) => $q->where('institution_id', $staffSelectedInstitutionId));

        $staffUsersTotal = (clone $staffUsersBase)->count();

        $staffUsers = (clone $staffUsersBase)
            // Eager-loaded for the "Peran & Wilayah" column's own $scopeDisplayFor() lookup, same
            // as "Semua User"'s own copy of this eager-load list above.
            ->with(['division', 'union.division', 'conference.union', 'church.conference', 'institution', 'assignedUnions'])
            // See "Semua User"'s own copy of this same search above for why only the row's own
            // direct region (never a parent/grandparent of it) is matched.
            ->when($staffSearch, fn ($q) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$staffSearch}%")
                    ->orWhere('email', 'like', "%{$staffSearch}%")
                    ->orWhereHas('division', fn ($q3) => $q3->where('name', 'like', "%{$staffSearch}%"))
                    ->orWhereHas('union', fn ($q3) => $q3->where('name', 'like', "%{$staffSearch}%"))
                    ->orWhereHas('conference', fn ($q3) => $q3->where('name', 'like', "%{$staffSearch}%"))
                    ->orWhereHas('church', fn ($q3) => $q3->where('name', 'like', "%{$staffSearch}%"))
                    ->orWhereHas('institution', fn ($q3) => $q3->where('name', 'like', "%{$staffSearch}%"))
            ))
            ->when($staffSort === 'name_asc', fn ($q) => $q->orderBy('name'))
            ->when($staffSort === 'name_desc', fn ($q) => $q->orderByDesc('name'))
            // "Wilayah" itself lives on a different table depending on each row's own level
            // (division_id -> divisions.name for a Divisi admin, union_id -> unions.name for a
            // Uni admin, and so on) — a correlated subquery per column, COALESCE'd together,
            // reads whichever one is actually set on that row without needing a join (which
            // would risk an ambiguous "id"/"name" column against every whereHas() above already
            // scoping this same query).
            ->when(in_array($staffSort, ['region_asc', 'region_desc'], true), fn ($q) => $q->orderByRaw(
                'COALESCE('
                    .'(SELECT name FROM divisions WHERE divisions.id = users.division_id), '
                    .'(SELECT name FROM unions WHERE unions.id = users.union_id), '
                    .'(SELECT name FROM conferences WHERE conferences.id = users.conference_id), '
                    .'(SELECT name FROM churches WHERE churches.id = users.church_id), '
                    .'(SELECT name FROM institutions WHERE institutions.id = users.institution_id)'
                .') '.($staffSort === 'region_desc' ? 'DESC' : 'ASC')
            ))
            ->paginate(50, ['*'], 'staff_page')
            // See the 'all' paginator's own comment above — same reasoning, applies here
            // identically.
            ->withQueryString()
            ->appends(['tab' => 'admin']);

        $scopeDataByLevel = [];

        if ($canBootstrapAnyLevel) {
            // Every branch below is unrestricted when $assignedUnionIds is null (SuperAdmin/
            // Admin Global) and Union-set-scoped otherwise (Admin Nasional) — same shape as the
            // admin/pimpinan listing query above. Only populated for levels actually in
            // $bootstrapLevels, so e.g. a scoped Admin Nasional never gets a 'nasional' picker
            // (they can't bootstrap into nasional itself — see UserPolicy::promote()).
            if (in_array('nasional', $bootstrapLevels, true)) {
                $scopeDataByLevel['nasional'] = Union::where('is_active', true)->orderBy('name')->get()
                    ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values();
            }

            // Not Union-set-scoped for Admin Nasional the way 'uni'/'daerah'/'gereja' below are —
            // Division is independent of Admin Nasional's own assigned Union set (per the user's
            // explicit call), so any active Division qualifies, mirroring
            // UserPolicy::scopeBelongsToAssignedUnions()'s own 'divisi' arm.
            if (in_array('divisi', $bootstrapLevels, true)) {
                $scopeDataByLevel['divisi'] = Division::where('is_active', true)->orderBy('name')->get()
                    ->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->values();
            }

            if (in_array('uni', $bootstrapLevels, true)) {
                $scopeDataByLevel['uni'] = Union::where('is_active', true)
                    ->when($assignedUnionIds !== null, fn ($q) => $q->whereIn('id', $assignedUnionIds))
                    ->orderBy('name')->get()
                    ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values();
            }

            if (in_array('daerah', $bootstrapLevels, true)) {
                $scopeDataByLevel['daerah'] = Conference::with('union')->where('is_active', true)
                    ->when($assignedUnionIds !== null, fn ($q) => $q->whereIn('union_id', $assignedUnionIds))
                    ->orderBy('name')->get()
                    ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->name} ({$c->union->name})"])->values();
            }

            if (in_array('gereja', $bootstrapLevels, true)) {
                $scopeDataByLevel['gereja'] = Church::with('conference')->where('is_active', true)
                    ->when($assignedUnionIds !== null, fn ($q) => $q->whereHas(
                        'conference', fn ($q2) => $q2->whereIn('union_id', $assignedUnionIds)
                    ))
                    ->orderBy('name')->get()
                    ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->name} ({$c->conference?->name})"])->values();
            }
        } else {
            $scopeOptions = $this->scopeOptions($actor, $targetLevel);
            if ($scopeOptions->isNotEmpty()) {
                $scopeDataByLevel[$targetLevel] = $scopeOptions->map(fn ($o) => ['id' => $o->id, 'label' => $o->name])->values();
            }
        }

        if ($canManageInstitutions) {
            $roles = array_merge($roles, [UserRole::AdminInstitusi, UserRole::PimpinanInstitusi]);
            $scopeDataByLevel['institusi'] = $institutionOptions->map(fn ($i) => ['id' => $i->id, 'label' => $i->name])->values();
        }

        // "admin mission dan diatasnya" per the user's explicit call — Daerah ("Mission" in
        // Adventist English usage) and every level above it, admin roles only (never a
        // read-only Pimpinan, matching UserPolicy::promote()'s own first check — approving one
        // of these IS a promotion). Gereja/Institusi-level admins never see this tab: a new
        // church is by definition outside their own single-church/institution remit.
        //
        // hasGlobalAccess() checked separately from level() — SuperAdmin's own level() is null
        // (see UserRole::level()'s own match), so the level()-list check alone would have
        // silently hidden this tab from SuperAdmin specifically, even though
        // AdminSuggestionPolicy::review() already authorized them to act on any row (confirmed
        // missing here, not just theoretical — SuperAdmin could approve/reject by hitting the
        // route directly, but the tab itself never showed up to find it from).
        $canReviewSuggestions = $actor->role !== null && ! $actor->role->isReadOnly()
            && ($actor->role->hasGlobalAccess() || in_array($actor->role->level(), ['daerah', 'uni', 'divisi', 'nasional', 'global'], true));

        $pendingSuggestions = collect();

        if ($canReviewSuggestions) {
            $pendingSuggestions = AdminSuggestion::query()
                ->where('status', AdminSuggestionStatus::Pending)
                ->visibleTo($actor)
                ->with(['user', 'person', 'conference.union'])
                ->orderBy('created_at')
                ->paginate(20, ['*'], 'saran_page')
                // See the 'all' paginator's own comment above — same reasoning, this tab's
                // pager must always point back to 'saran' regardless of whatever 'tab' the url
                // happened to carry before this link was generated.
                ->withQueryString()
                ->appends(['tab' => 'saran']);

            // Possible-duplicate hint per suggestion, computed once against every active church
            // nationwide (same reach as ChurchController::similar(), the same advisory this
            // app's own create-a-church forms already show) rather than re-querying per row —
            // per the user's explicit call, so the reviewer can catch "this is probably the same
            // church as X, already registered under a different name/spelling" before approving.
            // 'slug' included even though nothing here reads it directly — route('churches.edit', ...)
            // in suggestions-tab.blade.php needs it for Church's own {church:slug} route-key
            // binding (see routes/web.php); omitting it left that column null on every loaded
            // model, and route() throwing "Missing required parameter" the moment any suggestion
            // actually had a similar-name match — confirmed live, this crashed the whole Kelola
            // Pengguna page (regardless of which tab was open) for the very first pending
            // suggestion that ever triggered the hint.
            $activeChurches = Church::where('is_active', true)->with('conference')->get(['id', 'name', 'slug', 'conference_id']);

            // Stricter than the live "as you type" form hint's default 40% (see NameSimilarity's
            // own THRESHOLD) — a reviewer is about to act on this, not just get a passing typing
            // nudge, so a coincidental low-percent match (e.g. two unrelated churches that only
            // share a generic suffix) does more harm than good here. See also suggestions-tab
            // .blade.php's disclaimer text: even at this tighter cut, it's still only a system
            // hint, never a certainty the reviewer can skip judging for themself.
            $pendingSuggestions->getCollection()->transform(function ($suggestion) use ($activeChurches) {
                $suggestion->similarChurches = NameSimilarity::findSimilar($suggestion->church_name, $activeChurches, minPercent: 55);

                return $suggestion;
            });
        }

        // "Belum Ada Admin" — orgs that already exist (someone typed their name at some point)
        // but have nobody holding the matching admin role over them yet, per the user's explicit
        // request ("sudah ada namanya gereja, institusi, daerah, uni tapi belum ada yg diassign
        // sebagai admin"). Read-only (no inline "assign" action here — that's still the Admin
        // tab's own add-user form) so this is deliberately just a checklist of gaps to go act on
        // elsewhere. Each reuses that model's own scopeVisibleTo() rather than a hand-rolled
        // region filter, so an actor sees exactly the same orgs here they'd see anywhere else in
        // Kelola Akun/Analitik — Institusi is included only when $canManageInstitutions, matching
        // every other institution-related gate in this method.
        // Uni → Daerah cascading filter, matching Analitik & Grafik's own searchable-select UI
        // exactly (same partial, same regionFilterOptions() the user asked for) rather than a
        // plain <select> — applied on top of scopeVisibleTo() so it can only ever narrow, never
        // widen, an actor's own reach.
        $noAdminSelectedUnionId = $request->query('union_id');
        $noAdminSelectedConferenceId = $request->query('conference_id');

        [$noAdminUnionOptions, $noAdminConferenceOptions] = $this->regionFilterOptions($noAdminSelectedUnionId);

        $noAdminUnions = Union::query()->where('is_active', true)->visibleTo($actor)
            ->when($noAdminSelectedUnionId, fn ($q) => $q->where('id', $noAdminSelectedUnionId))
            ->whereDoesntHave('users', fn ($q) => $q->where('role', UserRole::AdminUni->value))
            ->orderBy('name')->get(['id', 'name', 'slug', 'division_id']);

        $noAdminConferences = Conference::query()->where('is_active', true)->visibleTo($actor)
            ->when($noAdminSelectedUnionId, fn ($q) => $q->where('union_id', $noAdminSelectedUnionId))
            ->when($noAdminSelectedConferenceId, fn ($q) => $q->where('id', $noAdminSelectedConferenceId))
            ->whereDoesntHave('users', fn ($q) => $q->where('role', UserRole::AdminDaerah->value))
            ->with('union')->orderBy('name')->get(['id', 'name', 'slug', 'union_id']);

        $noAdminChurches = Church::query()->where('is_active', true)->visibleTo($actor)
            ->when($noAdminSelectedUnionId, fn ($q) => $q->whereHas('conference', fn ($q2) => $q2->where('union_id', $noAdminSelectedUnionId)))
            ->when($noAdminSelectedConferenceId, fn ($q) => $q->where('conference_id', $noAdminSelectedConferenceId))
            ->whereDoesntHave('users', fn ($q) => $q->where('role', UserRole::AdminGereja->value))
            ->with('conference.union')->orderBy('name')->get(['id', 'name', 'slug', 'conference_id']);

        $noAdminInstitutions = $canManageInstitutions
            ? Institution::query()->where('is_active', true)->visibleTo($actor)
                ->when($noAdminSelectedUnionId, fn ($q) => $q->where(
                    fn ($q2) => $q2->where('union_id', $noAdminSelectedUnionId)
                        ->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $noAdminSelectedUnionId))
                ))
                ->when($noAdminSelectedConferenceId, fn ($q) => $q->where('conference_id', $noAdminSelectedConferenceId))
                ->whereDoesntHave('users', fn ($q) => $q->where('role', UserRole::AdminInstitusi->value))
                ->orderBy('name')->get(['id', 'name', 'slug'])
            : collect();

        $activeTab = in_array($request->query('tab'), ['admin', 'terhapus', 'saran', 'belum-admin'], true) ? $request->query('tab') : 'all';

        // Soft-deleted (destroy()'d) users still physically exist and can silently block a
        // restrictOnDelete FK elsewhere (Union/Conference/Church/Institution) — this is where
        // Superadmin reaches in to either restore one or purge it for good (manage-deleted-users
        // gate, see AppServiceProvider). Everyone else never sees this tab at all.
        $trashedUsers = $isSuperAdmin
            ? User::onlyTrashed()->with(['division', 'union', 'conference.union', 'church.conference.union', 'institution', 'assignedUnions'])->orderByDesc('deleted_at')->get()
            : collect();

        return view('admin.users.index', [
            'targetLevel' => $targetLevel,
            'activeTab' => $activeTab,
            'allUsers' => $allUsers,
            'allUsersTotal' => $allUsersTotal,
            'allUsersUnverifiedTotal' => $allUsersUnverifiedTotal,
            'allUsersUnassignedTotal' => $allUsersUnassignedTotal,
            'allSearch' => $allSearch,
            'allSort' => $allSort,
            'allVerification' => $allVerification,
            'allRole' => $allRole,
            'allRoleOptions' => $allRoleOptions,
            'staffUsers' => $staffUsers,
            'staffUsersTotal' => $staffUsersTotal,
            'staffSearch' => $staffSearch,
            'staffSort' => $staffSort,
            'staffRole' => $staffRole,
            'staffRoleOptions' => $staffRoleOptions,
            'staffSelectedUnionId' => $staffSelectedUnionId,
            'staffSelectedConferenceId' => $staffSelectedConferenceId,
            'staffUnionOptions' => $staffUnionOptions,
            'staffConferenceOptions' => $staffConferenceOptions,
            'staffSelectedInstitutionId' => $staffSelectedInstitutionId,
            'staffInstitutionOptions' => $staffInstitutionOptions,
            'roles' => $roles,
            'scopeDataByLevel' => $scopeDataByLevel,
            'canManageInstitutions' => $canManageInstitutions,
            'institutionOptions' => $institutionOptions,
            'isSuperAdmin' => $isSuperAdmin,
            'canBootstrapAnyLevel' => $canBootstrapAnyLevel,
            'trashedUsers' => $trashedUsers,
            'canReviewSuggestions' => $canReviewSuggestions,
            'pendingSuggestions' => $pendingSuggestions,
            'noAdminUnions' => $noAdminUnions,
            'noAdminConferences' => $noAdminConferences,
            'noAdminChurches' => $noAdminChurches,
            'noAdminInstitutions' => $noAdminInstitutions,
            'noAdminUnionOptions' => $noAdminUnionOptions,
            'noAdminConferenceOptions' => $noAdminConferenceOptions,
            'noAdminSelectedUnionId' => $noAdminSelectedUnionId,
            'noAdminSelectedConferenceId' => $noAdminSelectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $this->isUniView(),
        ]);
    }

    public function promote(Request $request, User $target): RedirectResponse
    {
        $actor = $request->user();
        $targetLevel = $actor->role?->promotesToLevel();

        abort_if($targetLevel === null, 403);

        $data = $request->validate(['role' => ['required', 'string']]);

        $newRole = UserRole::tryFrom($data['role']);
        abort_if($newRole === null, 422);

        // 'nasional' is the one level with a *set* of scopes rather than a single one — see
        // User::assignedUnions(). 'global' needs no scope at all (like 'nasional' used to,
        // before this level existed). Everything else is still a single required id.
        if ($newRole->level() === 'nasional') {
            $data += $request->validate([
                'scope_ids' => ['required', 'array', 'min:1'],
                'scope_ids.*' => ['integer'],
            ]);
        } else {
            $data += $request->validate([
                'scope_id' => [$newRole->level() === 'global' ? 'nullable' : 'required', 'integer'],
            ]);
        }

        Gate::authorize('promote', [$target, $newRole, $data['scope_id'] ?? null, $data['scope_ids'] ?? null]);

        $update = ['role' => $newRole, 'division_id' => null, 'union_id' => null, 'conference_id' => null, 'church_id' => null, 'institution_id' => null];

        // Keyed by the role being assigned, not the actor's own targetLevel — those only
        // diverge for superadmin's bootstrap capability and for institusi (see class
        // docblock), where a single actor may assign into any of several levels.
        $scopeColumn = match ($newRole->level()) {
            'divisi' => 'division_id',
            'uni' => 'union_id',
            'daerah' => 'conference_id',
            'gereja' => 'church_id',
            'institusi' => 'institution_id',
            default => null,
        };

        if ($scopeColumn) {
            $update[$scopeColumn] = $data['scope_id'];
        }

        $target->update($update);

        // Only meaningful for a 'nasional' target — sync() also clears any stale rows left over
        // if $target previously held a different Admin Nasional scope (or, cleared to [], any
        // left over from a prior stint as Admin Nasional before being re-promoted elsewhere).
        $target->assignedUnions()->sync($newRole->level() === 'nasional' ? $data['scope_ids'] : []);

        $scopeLabel = match (true) {
            $newRole->level() === 'nasional' => Union::whereIn('id', $data['scope_ids'])->pluck('name')->implode(', '),
            $scopeColumn === 'division_id' => Division::find($data['scope_id'])?->name,
            $scopeColumn === 'union_id' => Union::find($data['scope_id'])?->name,
            $scopeColumn === 'conference_id' => Conference::find($data['scope_id'])?->name,
            $scopeColumn === 'church_id' => Church::find($data['scope_id'])?->name,
            $scopeColumn === 'institution_id' => Institution::find($data['scope_id'])?->name,
            default => null,
        };

        AuditLogger::log(
            'user.promoted',
            $target,
            "Menugaskan \"{$target->name}\" sebagai {$newRole->label()}".($scopeLabel ? " ({$scopeLabel})" : '').'.'
        );

        // Only ever called from "Semua User"'s own "Jadikan Admin / Pimpinan" modal now (see
        // row-actions' own trigger and the static #promote-modal in index.blade.php), so
        // resolveUsersTab()'s 'all' fallback plus the all_* params below carry the actor's
        // filter state back the same way every other action on this page already does.
        return redirect()->route('admin.users.index', array_filter([
            'tab' => $this->resolveUsersTab($request),
            'all_search' => $request->input('all_search'),
            'all_sort' => $request->input('all_sort'),
            'all_verification' => $request->input('all_verification') !== 'all' ? $request->input('all_verification') : null,
            'all_role' => $request->input('all_role') !== 'all' ? $request->input('all_role') : null,
        ]))->with('status', __('users.assigned', ['name' => $target->name]));
    }

    public function revoke(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('revoke', $target);

        // Captured before the update wipes it — decides which tab to land back on, and
        // labels the audit entry with the role that's being taken away.
        $oldRole = $target->role;
        $wasInstitusi = $oldRole->level() === 'institusi';
        $wasReadOnly = $oldRole->isReadOnly();

        // union_id/conference_id/church_id are deliberately kept: once the role is gone
        // they revert to meaning "this member's home region" (see Lengkapi Profil / the
        // unassigned-list scoping in index() above) rather than "active assignment", so
        // regional admins can still find & re-promote this person later. institution_id
        // has no such dual meaning — institutions aren't part of the region hierarchy — so
        // it's cleared outright. assignedUnions (only ever populated for admin_nasional/
        // pimpinan_nasional) has no "home region" meaning either — a set of Unions isn't
        // something Lengkapi Profil or anything else re-derives, so it's cleared too.
        $target->update(['role' => null, 'institution_id' => null]);
        $target->assignedUnions()->detach();

        AuditLogger::log('user.revoked', $target, "Mencabut peran {$oldRole->label()} dari \"{$target->name}\".");

        // Historically always derived from the role being revoked, since the only existing
        // caller (user-row.blade.php's own "Cabut" button, in the Admin/Pemimpin/Institusi
        // tabs) never sent a 'tab' field at all — that fallback stays exactly as-is. "Semua
        // User"'s own Cabut button (see index.blade.php, next to row-actions) sends tab=all
        // explicitly, so it lands back there instead, same as every other action on this page.
        $tab = $request->input('tab') === 'all'
            ? 'all'
            : ($wasInstitusi ? 'institusi' : ($wasReadOnly ? 'pemimpin' : 'admin'));

        return redirect()->route('admin.users.index', array_filter([
            'tab' => $tab,
            'all_search' => $request->input('all_search'),
            'all_sort' => $request->input('all_sort'),
            'all_verification' => $request->input('all_verification') !== 'all' ? $request->input('all_verification') : null,
            'all_role' => $request->input('all_role') !== 'all' ? $request->input('all_role') : null,
        ]))->with('status', __('users.role_revoked', ['name' => $target->name]));
    }

    /**
     * Clears union_id/conference_id/church_id only — role and institution_id are left alone,
     * unlike revoke() above. Lets an admin detach a bogus or unwanted region link (e.g. a
     * church someone typed their own name into via Lengkapi Profil — see
     * FindsOrCreatesChurch::findOrCreateChurch()) without also stripping an active
     * Admin/Pimpinan's role. Deliberately allowed on an active role-holder too, per the user's
     * explicit call — this can leave a uni/daerah/gereja-level role without a working scope
     * until reassigned; the confirm dialog (see admin.users.index) warns about exactly that.
     *
     * For a role === null target, the bogus self-report this was built to clean up actually
     * lives on their linked Person now (see PersonController::resolveSelfReportedScope()), not
     * on this User row at all — so this also clears the linked Person's own
     * union_id/conference_id/church_id, or "release region" would silently do nothing for the
     * exact scenario in the doc comment above. An active role-holder's own Person self-report
     * is left untouched: that's a deliberate act from their own Profil Saya, a different thing
     * from releasing their admin-assigned scope here.
     */
    public function releaseRegion(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('releaseRegion', $target);

        $regionLabel = collect([$target->division?->name, $target->union?->name, $target->conference?->name, $target->church?->name])
            ->filter()
            ->implode(', ');

        if ($regionLabel === '' && $target->role === null) {
            $regionLabel = collect([$target->person?->union?->name, $target->person?->conference?->name, $target->person?->church?->name])
                ->filter()
                ->implode(', ');
        }

        $target->update(['division_id' => null, 'union_id' => null, 'conference_id' => null, 'church_id' => null]);

        if ($target->role === null) {
            $target->person?->update(['union_id' => null, 'conference_id' => null, 'church_id' => null]);
        }

        AuditLogger::log('user.region_released', $target, "Melepas wilayah \"{$regionLabel}\" dari \"{$target->name}\".");

        return $this->redirectToTab($request)->with('status', __('users.region_released', ['name' => $target->name]));
    }

    /**
     * "Ganti Wilayah" — the modal form for swapping an already role-assigned Admin/Pimpinan
     * Divisi/Uni/Daerah/Gereja/Institusi's region for a different one of the same level, without
     * going through releaseRegion() (clear) followed by a separate re-promote via "Semua User"'s
     * own "Jadikan Admin / Pimpinan" modal (which also meant hunting the now-unassigned member
     * back down in that tab first). Only offered for a role at one of those 5 levels — see
     * row-actions.blade.php's own guard, which still falls back to the plain release-only form
     * (releaseRegion() above) for a role === null target, since that scenario has no "level"
     * here to pick a same-level replacement from.
     *
     * Reuses the exact same [data-entity-modal-trigger]/[data-modal-ajax-form] contract as
     * edit()/update() above (see partials/entity-edit-modal.blade.php) — fetched as a fragment,
     * dropped into Kelola Pengguna's shared modal.
     */
    public function editRegion(Request $request, User $target)
    {
        Gate::authorize('releaseRegion', $target);

        $level = $target->role?->level();
        abort_unless(in_array($level, ['divisi', 'uni', 'daerah', 'gereja', 'institusi'], true), 404);

        $actor = $request->user();

        return view('admin.users.change-region', [
            'target' => $target,
            'tab' => $this->resolveUsersTab($request),
            'modal' => $request->boolean('modal'),
            'level' => $level,
            'levelLabel' => match ($level) {
                'divisi' => __('common.division'),
                'uni' => __('common.union'),
                'daerah' => __('common.conference'),
                'gereja' => __('common.church'),
                'institusi' => __('common.institution'),
            },
            'currentRegionName' => match ($level) {
                'divisi' => $target->division?->name,
                'uni' => $target->union?->name,
                'daerah' => $target->conference?->name,
                'gereja' => $target->church?->name,
                'institusi' => $target->institution?->name,
            },
            'currentScopeId' => match ($level) {
                'divisi' => $target->division_id,
                'uni' => $target->union_id,
                'daerah' => $target->conference_id,
                'gereja' => $target->church_id,
                'institusi' => $target->institution_id,
            },
            'scopeOptions' => $this->regionScopeOptionsForLevel($actor, $level),
        ]);
    }

    public function updateRegion(Request $request, User $target): Response
    {
        $level = $target->role?->level();
        abort_unless(in_array($level, ['divisi', 'uni', 'daerah', 'gereja', 'institusi'], true), 404);

        $data = $request->validate(['scope_id' => ['nullable', 'string']]);
        $scopeId = ($data['scope_id'] ?? '') !== '' ? (int) $data['scope_id'] : null;

        // Choosing "Tidak ada" (an empty scope_id) is really releaseRegion()'s own action —
        // authorize it the same way that already does, rather than promote()'s (which requires
        // a real, existing scope and would reject a null one outright). Choosing an actual
        // region re-uses promote()'s own policy check unchanged, so the new region is held to
        // exactly the same "does this fall within the actor's own reach" rule a normal
        // assignment would be.
        if ($scopeId === null) {
            Gate::authorize('releaseRegion', $target);
        } else {
            Gate::authorize('promote', [$target, $target->role, $scopeId]);
        }

        $oldRegionName = match ($level) {
            'divisi' => $target->division?->name,
            'uni' => $target->union?->name,
            'daerah' => $target->conference?->name,
            'gereja' => $target->church?->name,
            'institusi' => $target->institution?->name,
        };

        $scopeColumn = match ($level) {
            'divisi' => 'division_id',
            'uni' => 'union_id',
            'daerah' => 'conference_id',
            'gereja' => 'church_id',
            'institusi' => 'institution_id',
        };

        // Every scope column cleared, not just the 4 in the Divisi/Uni/Daerah/Gereja chain —
        // institution_id included too now that Institusi is one of the levels this covers, so
        // $scopeColumn is always the only one left set afterward regardless of which level this
        // target sits at.
        $target->update(array_merge(
            ['division_id' => null, 'union_id' => null, 'conference_id' => null, 'church_id' => null, 'institution_id' => null],
            [$scopeColumn => $scopeId]
        ));

        $newRegionName = match ($level) {
            'divisi' => Division::find($scopeId)?->name,
            'uni' => Union::find($scopeId)?->name,
            'daerah' => Conference::find($scopeId)?->name,
            'gereja' => Church::find($scopeId)?->name,
            'institusi' => Institution::find($scopeId)?->name,
        };

        AuditLogger::log(
            'user.region_changed',
            $target,
            $newRegionName !== null
                ? "Mengganti wilayah \"{$target->name}\" dari \"{$oldRegionName}\" menjadi \"{$newRegionName}\"."
                : "Melepas wilayah \"{$oldRegionName}\" dari \"{$target->name}\"."
        );

        $message = $newRegionName !== null
            ? __('users.region_changed', ['name' => $target->name, 'region' => $newRegionName])
            : __('users.region_released', ['name' => $target->name]);

        return $this->respondModalOrRedirect($request, 'admin.users.index', ['tab' => $this->resolveUsersTab($request)], 'status', $message);
    }

    /**
     * Mirrors index()'s own $scopeDataByLevel-building for a single, already-known level
     * (index() builds one per level it might need up front; this only ever needs the one
     * level a specific target already sits at) — same canBootstrapAnyLevel vs. scoped-actor
     * branching, same relations, so a scoped actor never sees (and never has authorized, per
     * promote()'s own policy check on submit) a region outside their own reach here either.
     */
    private function regionScopeOptionsForLevel(User $actor, string $level): array
    {
        $isGlobalAccess = $actor->role?->hasGlobalAccess() ?? false;
        $canBootstrapAnyLevel = $isGlobalAccess || $actor->role === UserRole::AdminNasional;
        $assignedUnionIds = $actor->role === UserRole::AdminNasional ? $actor->assignedUnionIds() : null;

        if (! $canBootstrapAnyLevel) {
            return $this->scopeOptions($actor, $level)->map(fn ($o) => ['id' => $o->id, 'label' => $o->name])->values()->all();
        }

        return match ($level) {
            'divisi' => Division::where('is_active', true)->orderBy('name')->get()
                ->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->values()->all(),
            'uni' => Union::where('is_active', true)
                ->when($assignedUnionIds !== null, fn ($q) => $q->whereIn('id', $assignedUnionIds))
                ->orderBy('name')->get()
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values()->all(),
            'daerah' => Conference::with('union')->where('is_active', true)
                ->when($assignedUnionIds !== null, fn ($q) => $q->whereIn('union_id', $assignedUnionIds))
                ->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->name} ({$c->union->name})"])->values()->all(),
            'gereja' => Church::with('conference')->where('is_active', true)
                ->when($assignedUnionIds !== null, fn ($q) => $q->whereHas(
                    'conference', fn ($q2) => $q2->whereIn('union_id', $assignedUnionIds)
                ))
                ->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->name} ({$c->conference?->name})"])->values()->all(),
            // Unscoped by $assignedUnionIds — Institusi sits outside the Divisi/Uni/Daerah/Gereja
            // tree entirely (no Union tie at all), matching index()'s own
            // $scopeDataByLevel['institusi'] and the Institusi tab's own always-unscoped
            // institutionAdmins/institutionPimpinan queries.
            'institusi' => Institution::where('is_active', true)->orderBy('name')->get()
                ->map(fn ($i) => ['id' => $i->id, 'label' => $i->name])->values()->all(),
            default => [],
        };
    }

    /**
     * The other half of the "kenapa gereja ini tidak bisa dihapus" loop: Kelola Akun's blocked-
     * delete tooltip (see AccountController::index()) already names the blocking user(s), but
     * hunting them down in Kelola Pengguna's "Semua User" tab one at a time (filtered by role)
     * was still the only way to actually clear them. This does the same union_id/conference_id/
     * church_id release as releaseRegion() above — on both the User row and, since every target
     * here is role === null, their linked Person's own self-reported region too (see
     * releaseRegion()'s doc comment) — but for a whole batch of ids submitted straight from that
     * Kelola Akun row. Deliberately restricted to role === null targets only: an active
     * Admin/Pimpinan losing their working scope should stay a deliberate, one-at-a-time decision
     * (see releaseRegion()'s confirm-dialog warning), not something a single bulk click on an
     * unrelated entity's row can do in passing.
     */
    public function releaseRegionBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $targets = User::whereIn('id', $data['user_ids'])->whereNull('role')->get();

        foreach ($targets as $target) {
            Gate::authorize('releaseRegion', $target);
        }

        $names = $targets->pluck('name')->implode(', ');

        User::whereIn('id', $targets->pluck('id'))->update(['division_id' => null, 'union_id' => null, 'conference_id' => null, 'church_id' => null]);
        Person::whereIn('user_id', $targets->pluck('id'))->update(['union_id' => null, 'conference_id' => null, 'church_id' => null]);

        foreach ($targets as $target) {
            AuditLogger::log('user.region_released', $target, "Melepas wilayah dari \"{$target->name}\" (aksi massal dari Kelola Akun).");
        }

        return back()->with('status', $targets->isEmpty()
            ? __('users.region_released_bulk_none')
            : __('users.region_released_bulk', ['count' => $targets->count(), 'names' => $names]));
    }

    public function destroy(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('delete', $target);

        $target->delete();

        AuditLogger::log('user.deleted', $target, "Menghapus akun \"{$target->name}\".");

        return $this->redirectToTab($request)->with('status', __('users.user_deleted', ['name' => $target->name]));
    }

    /**
     * "Semua User"'s own bulk "Non-Aktifkan Terpilih" — same shared external-form + select-all
     * checkbox pattern as Monitoring Antrean's Job Gagal/Batch Aktif/Batch Selesai (see
     * partials/bulk-select.blade.php). Every target authorized up front, in one pass, before any
     * row is actually touched — one target failing toggleActive (e.g. it's the actor's own row,
     * already excluded from this tab's own list but not a route param this endpoint can't be hit
     * with directly) aborts the whole batch rather than leaving it half-applied, same as
     * releaseRegionBulk()'s own shape above.
     */
    public function deactivateBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $targets = User::whereIn('id', $data['ids'])->get();

        foreach ($targets as $target) {
            Gate::authorize('toggleActive', $target);
        }

        User::whereIn('id', $targets->pluck('id'))->update(['is_active' => false]);

        foreach ($targets as $target) {
            AuditLogger::log('user.deactivated', $target, "Menonaktifkan akun \"{$target->name}\" (aksi massal).");
        }

        return $this->redirectToTab($request)->with('status', __('users.deactivated_bulk', ['count' => $targets->count()]));
    }

    /** "Semua User"'s own bulk "Hapus Terpilih" — see deactivateBulk()'s own doc comment above for the shared shape. */
    public function destroyBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $targets = User::whereIn('id', $data['ids'])->get();

        foreach ($targets as $target) {
            Gate::authorize('delete', $target);
        }

        $count = $targets->count();

        foreach ($targets as $target) {
            AuditLogger::log('user.deleted', $target, "Menghapus akun \"{$target->name}\" (aksi massal).");
            $target->delete();
        }

        return $this->redirectToTab($request)->with('status', __('users.deleted_bulk', ['count' => $count]));
    }

    /**
     * "Semua User"'s own bulk "Kirim Ulang OTP" — see deactivateBulk()'s own doc comment above
     * for the shared bulk-select shape. Silently drops any already-verified id from the
     * selection (rather than aborting the whole batch on one, the way the singular resendOtp()
     * above rejects a single already-verified target with its own 422) since a broad
     * "select all" click plausibly catches some verified accounts too — that's an expected,
     * harmless mismatch here, not a mistake worth failing the batch over. Synchronous per
     * account, same as the singular action (no queue) — fine at this list's realistic scale, but
     * a genuinely large selection would be slow to send this way.
     */
    public function resendOtpBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $targets = User::whereIn('id', $data['ids'])->whereNull('email_verified_at')->get();

        foreach ($targets as $target) {
            Gate::authorize('resendOtp', $target);
        }

        if ($targets->isEmpty()) {
            return $this->redirectToTab($request)->with('status', __('users.otp_resend_bulk_none'));
        }

        $sentCount = 0;

        foreach ($targets as $target) {
            if ($target->sendVerificationOtp()) {
                $sentCount++;
            }
        }

        return $this->redirectToTab($request)->with('status', __('users.otp_resent_bulk', ['count' => $sentCount, 'total' => $targets->count()]));
    }

    public function restore(User $target): RedirectResponse
    {
        $target->restore();

        AuditLogger::log('user.restored', $target, "Memulihkan akun \"{$target->name}\".");

        return redirect()->route('admin.users.index', ['tab' => 'terhapus'])
            ->with('status', __('users.user_restored', ['name' => $target->name]));
    }

    /**
     * Unlike destroy() above (a soft delete), this is permanent — the whole point of this
     * action is clearing a row that's still physically blocking a restrictOnDelete FK
     * elsewhere despite being "deleted" from every normal list.
     */
    public function forceDelete(User $target): RedirectResponse
    {
        $name = $target->name;
        $target->forceDelete();

        AuditLogger::log('user.force_deleted', $target, "Menghapus permanen akun \"{$name}\".");

        return redirect()->route('admin.users.index', ['tab' => 'terhapus'])
            ->with('status', __('users.user_force_deleted', ['name' => $name]));
    }

    /**
     * A minimal edit page for the target's own login/display name (User.name) — distinct
     * from Person.name, which is the separate public-directory profile name edited via
     * people/{person} instead. Reached from Kelola Pengguna's row-actions, so $tab comes
     * through the query string to redirect back to the right tab on save (see redirectToTab()).
     */
    public function edit(Request $request, User $target)
    {
        Gate::authorize('update', $target);

        return view('admin.users.edit', [
            'target' => $target,
            'tab' => $this->resolveUsersTab($request),
            'modal' => $request->boolean('modal'),
        ]);
    }

    public function update(Request $request, User $target): Response
    {
        Gate::authorize('update', $target);

        $data = $this->validateOrRespondModal($request, [
            'name' => ['required', 'string', 'max:255'],
        ], 'admin.users.edit', ['target' => $target, 'tab' => $this->resolveUsersTab($request)]);

        if ($data instanceof Response) {
            return $data;
        }

        $target->update($data);

        AuditLogger::log('user.updated', $target, "Memperbarui nama akun menjadi \"{$target->name}\".");

        // Same tab/filter preservation as redirectToTab() (see that method's own doc comment) —
        // duplicated rather than reused since redirectToTab() returns a RedirectResponse
        // outright, but a modal-originated submit needs the route/params instead so
        // respondModalOrRedirect() can choose between that and a JSON redirect.
        return $this->respondModalOrRedirect($request, 'admin.users.index', array_filter([
            'tab' => $this->resolveUsersTab($request),
            'all_search' => $request->input('all_search'),
            'all_sort' => $request->input('all_sort'),
            'all_verification' => $request->input('all_verification') !== 'all' ? $request->input('all_verification') : null,
            'all_role' => $request->input('all_role') !== 'all' ? $request->input('all_role') : null,
        ]), 'status', __('users.user_updated', ['name' => $target->name]));
    }

    /** Shared by edit() (reading ?tab= on the GET) and update() (reading the form's own hidden tab field on submit) — see admin/users/edit.blade.php. */
    private function resolveUsersTab(Request $request): string
    {
        return in_array($request->input('tab'), ['admin', 'terhapus'], true)
            ? $request->input('tab')
            : 'all';
    }

    public function toggleActive(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('toggleActive', $target);

        $target->update(['is_active' => ! $target->is_active]);

        $status = $target->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $target->is_active ? 'user.activated' : 'user.deactivated',
            $target,
            ($target->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." akun \"{$target->name}\"."
        );

        return $this->redirectToTab($request)->with('status', __('users.user_status_changed', ['name' => $target->name, 'status' => $status]));
    }

    public function resendOtp(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('resendOtp', $target);

        abort_if($target->hasVerifiedEmail(), 422);

        $sent = $target->sendVerificationOtp();

        return $sent
            ? $this->redirectToTab($request)->with('status', __('users.otp_resent_to', ['email' => $target->email]))
            : $this->redirectToTab($request)->with('error', __('users.otp_resend_failed', ['email' => $target->email]));
    }

    /**
     * destroy()/toggleActive()/resendOtp() are all called from row-actions.blade.php, shared
     * across 4 tabs — tab-switching there is client-side only (see partials/tab-script.blade.php),
     * so the URL never reflects whichever tab is actually visible when the form submits. back()
     * would instead redirect to whatever tab was in the URL at page-load time, landing the admin
     * on the wrong tab. Each form carries its own tab as a hidden field so this can redirect to
     * the tab the row actually lives in, not whatever the URL happened to say.
     */
    private function redirectToTab(Request $request): RedirectResponse
    {
        $tab = in_array($request->input('tab'), ['admin', 'terhapus'], true)
            ? $request->input('tab')
            : 'all';

        // all_search/all_sort/all_verification/all_role only ever arrive here from "Semua
        // User"'s own row-actions (see that partial's own doc comment) — array_filter() drops
        // them entirely for every other tab's action instead of appending empty query params.
        // Without this, an action taken while that tab's list had a search term, a non-default
        // sort, and/or a verification/role filter applied used to reset all of them back to
        // defaults on redirect, even though $tab itself was already correctly preserved —
        // confirmed live via the "Kirim OTP" button losing the active sort. all_verification/
        // all_role's own default is the string 'all' rather than false, so those two are dropped
        // explicitly rather than via array_filter() alone (which only strips falsy values, and
        // 'all' isn't one).
        return redirect()->route('admin.users.index', array_filter([
            'tab' => $tab,
            'all_search' => $request->input('all_search'),
            'all_sort' => $request->input('all_sort'),
            'all_verification' => $request->input('all_verification') !== 'all' ? $request->input('all_verification') : null,
            'all_role' => $request->input('all_role') !== 'all' ? $request->input('all_role') : null,
        ]));
    }

    /**
     * "Semua User" and "Daftar Admin & Pimpinan"'s shared region scope — every user (role=null
     * or any role) whose own region falls within the actor's reach. Institution accounts sit
     * outside the Divisi/Uni/Daerah/Gereja tree entirely (no Union tie at all — see index()'s
     * own "Institusi" comment above), so they're included unconditionally whenever
     * $canManageInstitutions is true rather than a narrower rule nothing else on this page
     * follows.
     *
     * Every relation walked below (union, conference, church, conference.union,
     * church.conference(.union), person, person.union, person.conference(.union),
     * assignedUnions) is one already proven correct elsewhere in this controller — a
     * Person-based reach for role=null users combined with a relation-based reach for
     * role-assigned ones, rather than hand-rolling a new, unverified shape.
     */
    private function applyAllUsersScope($query, User $actor, string $targetLevel, ?array $assignedUnionIds, bool $canManageInstitutions)
    {
        return $query->where(function ($q) use ($actor, $targetLevel, $assignedUnionIds, $canManageInstitutions) {
            $matchedAnyClause = false;

            if ($canManageInstitutions) {
                $matchedAnyClause = true;
                $q->orWhereIn('role', [UserRole::AdminInstitusi->value, UserRole::PimpinanInstitusi->value]);
            }

            if ($assignedUnionIds !== null) {
                // Scoped Admin Nasional — their own assigned Union set, same reach as the
                // bootstrap branches above (union_id/conference/church.conference directly on the
                // User row for role-assigned accounts, assignedUnions for a Nasional-level one,
                // person/person.conference for role=null members).
                $matchedAnyClause = true;
                $q->orWhere(fn ($q2) => $q2
                    ->whereIn('union_id', $assignedUnionIds)
                    ->orWhereHas('conference', fn ($q3) => $q3->whereIn('union_id', $assignedUnionIds))
                    ->orWhereHas('church.conference', fn ($q3) => $q3->whereIn('union_id', $assignedUnionIds))
                    ->orWhereHas('assignedUnions', fn ($q3) => $q3->whereIn('unions.id', $assignedUnionIds))
                    ->orWhereHas('person', fn ($q3) => $q3
                        ->whereIn('union_id', $assignedUnionIds)
                        ->orWhereHas('conference', fn ($q4) => $q4->whereIn('union_id', $assignedUnionIds))));
            }

            if ($targetLevel === 'uni') {
                $matchedAnyClause = true;
                $q->orWhere(fn ($q2) => $q2
                    ->whereHas('union', fn ($q3) => $q3->where('division_id', $actor->division_id))
                    ->orWhereHas('conference.union', fn ($q3) => $q3->where('division_id', $actor->division_id))
                    ->orWhereHas('church.conference.union', fn ($q3) => $q3->where('division_id', $actor->division_id))
                    ->orWhereHas('person', fn ($q3) => $q3
                        ->whereHas('union', fn ($q4) => $q4->where('division_id', $actor->division_id))
                        ->orWhereHas('conference.union', fn ($q4) => $q4->where('division_id', $actor->division_id))));
            }

            if ($targetLevel === 'daerah') {
                $matchedAnyClause = true;
                $q->orWhere(fn ($q2) => $q2
                    ->where('union_id', $actor->union_id)
                    ->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $actor->union_id))
                    ->orWhereHas('church.conference', fn ($q3) => $q3->where('union_id', $actor->union_id))
                    ->orWhereHas('person', fn ($q3) => $q3
                        ->where('union_id', $actor->union_id)
                        ->orWhereHas('conference', fn ($q4) => $q4->where('union_id', $actor->union_id))));
            }

            if ($targetLevel === 'gereja') {
                $matchedAnyClause = true;
                $q->orWhere(fn ($q2) => $q2
                    ->where('conference_id', $actor->conference_id)
                    ->orWhereHas('church', fn ($q3) => $q3->where('conference_id', $actor->conference_id))
                    ->orWhereHas('person', fn ($q3) => $q3->where('conference_id', $actor->conference_id)));
            }

            // No clause ever matched (shouldn't happen — every non-global actor reaching this
            // point has a $targetLevel of uni/daerah/gereja, or is a scoped Admin Nasional with
            // $assignedUnionIds set) — fail closed rather than silently falling through to an
            // unscoped WHERE that would show everyone.
            if (! $matchedAnyClause) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    private function scopeOptions(User $actor, string $targetLevel)
    {
        return match ($targetLevel) {
            'uni' => Union::where('division_id', $actor->division_id)->where('is_active', true)->orderBy('name')->get(),
            'daerah' => Conference::where('union_id', $actor->union_id)->where('is_active', true)->orderBy('name')->get(),
            'gereja' => Church::where('conference_id', $actor->conference_id)->where('is_active', true)->orderBy('name')->get(),
            default => collect(),
        };
    }

    private function adminRoleForLevel(string $level): UserRole
    {
        return match ($level) {
            'global' => UserRole::AdminGlobal,
            'nasional' => UserRole::AdminNasional,
            'divisi' => UserRole::AdminDivisi,
            'uni' => UserRole::AdminUni,
            'daerah' => UserRole::AdminDaerah,
            'gereja' => UserRole::AdminGereja,
        };
    }

    private function pimpinanRoleForLevel(string $level): UserRole
    {
        return match ($level) {
            'global' => UserRole::PimpinanGlobal,
            'nasional' => UserRole::PimpinanNasional,
            'divisi' => UserRole::PimpinanDivisi,
            'uni' => UserRole::PimpinanUni,
            'daerah' => UserRole::PimpinanDaerah,
            'gereja' => UserRole::PimpinanGereja,
        };
    }

    private function rolesForLevel(string $level): array
    {
        return match ($level) {
            'nasional' => [UserRole::AdminNasional, UserRole::PimpinanNasional],
            'divisi' => [UserRole::AdminDivisi, UserRole::PimpinanDivisi],
            'uni' => [UserRole::AdminUni, UserRole::PimpinanUni],
            'daerah' => [UserRole::AdminDaerah, UserRole::PimpinanDaerah],
            'gereja' => [UserRole::AdminGereja, UserRole::PimpinanGereja],
            default => [],
        };
    }
}
