<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
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

        return view('settings.edit', ['settings' => $settings, 'nextRun' => $nextRun]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_fetch_enabled' => ['nullable', 'boolean'],
            'auto_fetch_day' => ['required', 'integer', 'min:0', 'max:6'],
            'auto_fetch_time' => ['required', 'date_format:H:i'],
        ]);

        $data['auto_fetch_enabled'] = $request->boolean('auto_fetch_enabled');

        AppSetting::current()->update($data);

        return back()->with('status', 'Pengaturan jadwal berhasil disimpan.');
    }
}
