<?php

namespace App\Http\Controllers;

use App\Enums\GroupPlatform;
use App\Models\AppSetting;
use App\Models\ChurchSocial;
use App\Models\CoordinatorGroup;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private const TABS = ['general', 'platform', 'coordinator'];

    public function edit(Request $request)
    {
        $settings = AppSetting::current();

        $nextRun = null;

        if ($settings->auto_fetch_enabled) {
            [$hour, $minute] = explode(':', $settings->auto_fetch_time);

            $now = Carbon::now('Asia/Jakarta');
            $nextRun = $now->copy()
                ->startOfWeek(Carbon::SUNDAY)
                ->addDays($settings->auto_fetch_day)
                ->setTime((int) $hour, (int) $minute);

            if ($nextRun->lessThanOrEqualTo($now)) {
                $nextRun->addWeek();
            }
        }

        // Bypasses the enabledPlatform scope deliberately — shown regardless of a
        // platform's current toggle state, so disabling one doesn't also hide how many
        // accounts it would affect. ->value explicitly, since `platform` is enum-cast —
        // plucking it directly would key the array by enum instances, not plain strings.
        //
        // is_active = true is NOT optional the way the scope bypass above is — "delete" on a
        // social account (see ChurchSocialController::destroy()) never actually removes the
        // row, only sets is_active = false, same soft-hide convention as every other entity in
        // this app. Every dashboard total (Total Akun Media Sosial, Jangkauan, Distribution
        // Channels) already filters to is_active accounts only via activeSocials() and friends
        // — omitting this filter here (a real, confirmed bug, not a deliberate design choice
        // like the scope bypass) silently counted every account ever "removed" too, inflating
        // this number past what the dashboard's own totals ever show for the exact same data.
        $platformAccountCounts = ChurchSocial::withoutGlobalScope('enabledPlatform')
            ->where('is_active', true)
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->platform->value => $row->total]);

        // For the Koordinator Global tab's Union list — every active Union, regardless of
        // whether it has its own coordinator contact set yet (that's the point: a global-level
        // actor can see and fill in the gaps admin_uni hasn't gotten to).
        $unions = Union::where('is_active', true)->orderBy('name')->with('groups')->get(['id', 'slug', 'name', 'coordinator_whatsapp_number']);

        $globalGroups = CoordinatorGroup::whereNull('union_id')->get();

        $activeTab = $this->resolveTab($request, $request->query('tab'));

        return view('settings.edit', [
            'settings' => $settings,
            'nextRun' => $nextRun,
            'platformAccountCounts' => $platformAccountCounts,
            'unions' => $unions,
            'globalGroups' => $globalGroups,
            'activeTab' => $activeTab,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_fetch_enabled' => ['nullable', 'boolean'],
            'auto_fetch_day' => ['required', 'integer', 'min:0', 'max:6'],
            'auto_fetch_time' => ['required', 'date_format:H:i'],
            'cs_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'groups' => ['nullable', 'array'],
            'groups.*.platform' => ['nullable', Rule::enum(GroupPlatform::class)],
            'groups.*.url' => ['nullable', 'url', 'max:2048'],
            'apify_fallback_to_manual' => ['nullable', 'boolean'],
            'apify_token' => ['nullable', 'string', 'max:255'],
            'youtube_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $data['auto_fetch_enabled'] = $request->boolean('auto_fetch_enabled');
        $data['apify_fallback_to_manual'] = $request->boolean('apify_fallback_to_manual');

        // Both key fields always render blank (see settings/edit.blade.php) so an existing
        // secret never round-trips to the browser — an empty submission therefore means "didn't
        // touch it", not "clear it", so each is dropped from the update rather than nulling out
        // an already-configured key.
        foreach (['apify_token', 'youtube_api_key'] as $secretField) {
            if (! $request->filled($secretField)) {
                unset($data[$secretField]);
            }
        }

        // Superadmin-only (see settings/edit.blade.php's @can wrap around the whole
        // card) — processed only when authorized, and skipped entirely otherwise, so a
        // non-superadmin submitting the rest of this form (they can reach manage-settings
        // more broadly) can't have platform toggles they never saw silently reset to
        // false by their own request's absent checkboxes.
        if ($request->user()->can('manage-platform-visibility')) {
            foreach (['youtube', 'instagram', 'tiktok', 'facebook', 'x', 'threads'] as $platform) {
                $data["{$platform}_enabled"] = $request->boolean("{$platform}_enabled");
            }
        }

        // Global chat groups (WhatsApp/Messenger/…) now live in their own table — see
        // CoordinatorGroup — since a scope can hold several at once. Simplest correct sync for
        // an admin-facing list this small: replace the whole set rather than diffing rows;
        // blank rows (added then left empty) are dropped instead of rejected, so an
        // accidental extra "+ Tambah Group" click never blocks saving the rest of the form.
        $groups = collect($data['groups'] ?? [])->filter(fn ($g) => filled($g['url'] ?? null) && filled($g['platform'] ?? null));
        unset($data['groups']);

        CoordinatorGroup::whereNull('union_id')->delete();
        $groups->each(fn ($g) => CoordinatorGroup::create(['union_id' => null, 'platform' => $g['platform'], 'url' => $g['url']]));

        AppSetting::current()->update($data);

        // Not back(): the tabs are client-side only (see partials/tab-script), so the URL never
        // reflects whichever tab was actually visible when this submitted — the form carries its
        // own hidden tab field (see settings/edit.blade.php) so saving lands back on the same
        // tab instead of always snapping to the first one.
        $tab = $this->resolveTab($request, $request->input('tab'));

        return redirect()->route('settings.edit', ['tab' => $tab])->with('status', __('settings.saved'));
    }

    /**
     * Falls back to 'general' both for an unrecognized tab AND for 'platform' when the viewer
     * can't see that tab at all (settings/edit.blade.php only renders its button/panel behind
     * the same manage-platform-visibility check) — otherwise a stale/tampered ?tab=platform
     * would tell the tab script to activate a panel that was never rendered for this viewer,
     * leaving the page showing nothing.
     */
    private function resolveTab(Request $request, ?string $tab): string
    {
        if ($tab === 'platform' && ! $request->user()->can('manage-platform-visibility')) {
            return 'general';
        }

        return in_array($tab, self::TABS, true) ? $tab : 'general';
    }
}
