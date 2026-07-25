<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function edit()
    {
        $goals = collect(Goal::METRICS)->mapWithKeys(fn ($metric) => [$metric => Goal::forMetric($metric)]);

        return view('goals.edit', ['goals' => $goals]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_year' => ['required', 'array'],
            'target_value' => ['required', 'array'],
        ] + collect(Goal::METRICS)->mapWithKeys(fn ($metric) => [
            "target_year.{$metric}" => ['required', 'integer', 'min:2000', 'max:2200'],
            "target_value.{$metric}" => ['required', 'integer', 'min:0'],
        ])->all());

        foreach (Goal::METRICS as $metric) {
            Goal::forMetric($metric)->update([
                'target_year' => $data['target_year'][$metric],
                'target_value' => $data['target_value'][$metric],
                'updated_by' => $request->user()->id,
            ]);
        }

        return back()->with('status', __('goals.saved'));
    }
}
