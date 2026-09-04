<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kelola Akun's row actions (toggle-active/destroy, spread across 6 different entity
 * controllers — Division/Union/Conference/Institution/Church/Person) used to always bounce back
 * to a blank, unfiltered/default-sorted tab regardless of what was actually being viewed:
 * row-actions.blade.php never carried any hidden filter state, and every one of those six
 * controllers' redirects hardcoded just ['tab' => '<fixed>'] with no query-string reconstruction
 * at all. Mirrors the same fix already applied to Kelola Pengguna
 * (UserAssignmentController::redirectToTab()), just generalized into a shared trait since it
 * needs reusing across 6 controller classes rather than living privately in one — and this page
 * uses per-tab-prefixed field names (search_uni vs search_daerah, etc.) plus up to two
 * region-filter ids per tab, instead of Kelola Pengguna's one shared search/sort pair.
 */
trait RedirectsToAccountsTab
{
    /** Each tab's own region-filter field name(s), beyond the search_{tab}/sort_{tab} pair every tab has. */
    private const ACCOUNTS_TAB_FILTER_FIELDS = [
        'divisi' => [],
        'uni' => ['division_id_uni'],
        'daerah' => ['union_id_daerah'],
        'gereja' => ['union_id_gereja', 'conference_id_gereja'],
        'institusi' => ['union_id_institusi', 'conference_id_institusi'],
        'personal' => ['union_id_personal', 'conference_id_personal'],
    ];

    private function redirectToAccountsTab(Request $request, string $tab): RedirectResponse
    {
        $fields = array_merge(['search_'.$tab, 'sort_'.$tab], self::ACCOUNTS_TAB_FILTER_FIELDS[$tab] ?? []);

        $params = ['tab' => $tab];

        foreach ($fields as $field) {
            $params[$field] = $request->input($field);
        }

        // array_filter() drops every field that's null/'' (not present on this request, e.g. a
        // sort/region-filter value the row-action form never received) instead of appending
        // empty query params that would otherwise litter the redirect URL.
        return redirect()->route('admin.accounts.index', array_filter($params));
    }
}
