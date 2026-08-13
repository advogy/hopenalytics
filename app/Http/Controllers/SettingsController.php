<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ChurchSocial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SettingsController extends Controller
{
    public function edit()
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
        $platformAccountCounts = ChurchSocial::withoutGlobalScope('enabledPlatform')
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->platform->value => $row->total]);

        return view('settings.edit', [
            'settings' => $settings,
            'nextRun' => $nextRun,
            'platformAccountCounts' => $platformAccountCounts,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_fetch_enabled' => ['nullable', 'boolean'],
            'auto_fetch_day' => ['required', 'integer', 'min:0', 'max:6'],
            'auto_fetch_time' => ['required', 'date_format:H:i'],
            'cs_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'cs_whatsapp_group_link' => ['nullable', 'url', 'max:2048'],
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
            foreach (['youtube', 'instagram', 'tiktok', 'facebook', 'x'] as $platform) {
                $data["{$platform}_enabled"] = $request->boolean("{$platform}_enabled");
            }
        }

        AppSetting::current()->update($data);

        return back()->with('status', __('settings.saved'));
    }
}
