<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Union;
use Illuminate\Http\Request;

class HierarchyController extends Controller
{
    /**
     * One page, four tabs (Uni / Daerah / Gereja / Institusi) — same tab pattern as the
     * account directory (resources/views/partials/tab-script.blade.php), instead of
     * separate admin pages for what is really one "organizational structure" concern.
     * Institusi isn't nested under Uni/Daerah (see UserRole::level()), so it has no
     * parent-count columns the way Daerah/Gereja do. Each tab paginates & searches
     * independently (distinct query-string page names) since all four render at once and
     * are just toggled client-side.
     */
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['daerah', 'gereja', 'institusi'], true) ? $request->query('tab') : 'uni';

        $searchUni = trim((string) $request->query('search_uni'));
        $searchDaerah = trim((string) $request->query('search_daerah'));
        $searchGereja = trim((string) $request->query('search_gereja'));
        $searchInstitusi = trim((string) $request->query('search_institusi'));

        // Tab switching is client-side only (no page reload — see partials/tab-script.blade.php),
        // so a pagination link built from the *current* request's query string would still
        // carry whatever tab was active on page load, not whatever tab the visitor is actually
        // looking at when they click it. appends(['tab' => ...]) forces each paginator's own
        // links to always point back to its own tab, regardless of what was in the URL when
        // the page was first rendered.
        $unions = Union::withCount(['conferences', 'people', 'users'])
            ->when($searchUni, fn ($q) => $q->where('name', 'like', "%{$searchUni}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'uni_page')
            ->withQueryString()
            ->appends(['tab' => 'uni']);

        $conferences = Conference::with('union')->withCount(['churches', 'people', 'users'])
            ->when($searchDaerah, fn ($q) => $q->where('name', 'like', "%{$searchDaerah}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'daerah_page')
            ->withQueryString()
            ->appends(['tab' => 'daerah']);

        // No is_active filter here (unlike the rest of the app's church queries): this is the
        // management view, so a deactivated church still needs to show up — otherwise there'd
        // be no way to find it again and toggle it back on.
        $churches = Church::with('conference.union')->withCount('users')
            ->when($searchGereja, fn ($q) => $q->where('name', 'like', "%{$searchGereja}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'gereja_page')
            ->withQueryString()
            ->appends(['tab' => 'gereja']);

        $institutions = Institution::withCount('users')
            ->when($searchInstitusi, fn ($q) => $q->where('name', 'like', "%{$searchInstitusi}%"))
            ->orderBy('name')
            ->paginate(20, ['*'], 'institusi_page')
            ->withQueryString()
            ->appends(['tab' => 'institusi']);

        return view('admin.hierarchy.index', [
            'activeTab' => $activeTab,
            'unions' => $unions,
            'conferences' => $conferences,
            'churches' => $churches,
            'institutions' => $institutions,
            'searchUni' => $searchUni,
            'searchDaerah' => $searchDaerah,
            'searchGereja' => $searchGereja,
            'searchInstitusi' => $searchInstitusi,
        ]);
    }
}
