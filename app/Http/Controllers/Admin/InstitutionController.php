<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Union;
use App\Services\GeocodingService;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstitutionController extends Controller
{
    public function create(Request $request)
    {
        return view('admin.institutions.form', ['institution' => new Institution] + $this->regionPickerData($request));
    }

    /** Advisory "did you mean" lookup for the name field — see NameSimilarity. */
    public function similar(Request $request): JsonResponse
    {
        $matches = NameSimilarity::findSimilar(
            (string) $request->query('name', ''),
            Institution::where('is_active', true)
                ->when($request->query('exclude_id'), fn ($q, $id) => $q->whereKeyNot($id))
                ->with(['conference.union', 'union'])
                ->get(['id', 'slug', 'name', 'union_id', 'conference_id']),
        );

        return response()->json($matches->map(fn ($m) => [
            'name' => $m['model']->name,
            'context' => $m['model']->conference?->name ?? $m['model']->union?->name,
            'url' => route('admin.institutions.edit', $m['model']),
        ])->values());
    }

    public function store(Request $request, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $data['slug'] = $this->uniqueSlug($data['name']);
        [$data['union_id'], $data['conference_id']] = $this->resolveRegion($request, null, null);

        if ($data['latitude'] !== null && $data['longitude'] !== null) {
            $data['geocoded_at'] = null;
        } else {
            $this->applyGeocoding($data, $geocoding);
        }

        $institution = Institution::create($data);

        AuditLogger::log('institution.created', $institution, "Menambahkan Institusi \"{$institution->name}\".");

        // Straight to Kelola Akun Media Sosial rather than the accounts list — adding social
        // accounts is always the very next thing an admin does right after creating an entity,
        // per the user's explicit call (see ChurchController::store()).
        return redirect()->route('admin.institutions.socials.index', $institution)->with('status', __('accounts.entity_created', ['entity' => __('common.institution'), 'name' => $institution->name]));
    }

    public function edit(Request $request, Institution $institution)
    {
        return view('admin.institutions.form', ['institution' => $institution] + $this->regionPickerData($request));
    }

    public function update(Request $request, Institution $institution, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $coordsManuallyChanged = $data['latitude'] !== $institution->latitude || $data['longitude'] !== $institution->longitude;

        if ($coordsManuallyChanged) {
            $data['geocoded_at'] = null;
        } elseif ($data['city'] !== $institution->city || $data['country'] !== $institution->country) {
            $this->applyGeocoding($data, $geocoding);
        }

        [$data['union_id'], $data['conference_id']] = $this->resolveRegion($request, $institution->union_id, $institution->conference_id);

        $institution->update($data);

        AuditLogger::log('institution.updated', $institution, "Memperbarui Institusi \"{$institution->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])->with('status', __('accounts.entity_updated', ['entity' => __('common.institution'), 'name' => $institution->name]));
    }

    /**
     * Never trust a client-submitted region assignment: an admin_daerah is always pinned to
     * their own Uni+Daerah; an admin_uni is pinned to their own Uni, only the Daerah-under-it
     * picker (regionPickerData() below) is a real choice for them, and it's re-validated here
     * against their own union regardless; a scoped admin_nasional's submission is re-validated
     * against their own assigned Union set (falling back to blank/nasional rather than their
     * prior value, same as admin_uni's conference re-validation above); global-level (superadmin/
     * admin_global) is trusted as-is, including leaving both blank for a nasional institution.
     * Whatever conference ends up selected always forces its own union_id too, so the
     * denormalized invariant (Institution::scopeVisibleTo()) never drifts even if a global
     * actor's picker submits a stale/omitted union_id.
     */
    private function resolveRegion(Request $request, ?int $currentUnionId, ?int $currentConferenceId): array
    {
        $user = $request->user();
        $submittedConferenceId = $request->filled('conference_id') ? (int) $request->input('conference_id') : null;
        $submittedUnionId = $request->filled('union_id') ? (int) $request->input('union_id') : null;

        [$unionId, $conferenceId] = match ($user->role->level()) {
            'daerah' => [$user->union_id, $user->conference_id],
            'uni' => [
                $user->union_id,
                $submittedConferenceId && Conference::whereKey($submittedConferenceId)->where('union_id', $user->union_id)->exists()
                    ? $submittedConferenceId
                    : null,
            ],
            'nasional' => [
                $submittedUnionId && in_array($submittedUnionId, $user->assignedUnionIds(), true) ? $submittedUnionId : null,
                $submittedConferenceId && Conference::whereKey($submittedConferenceId)->whereIn('union_id', $user->assignedUnionIds())->exists()
                    ? $submittedConferenceId
                    : null,
            ],
            'divisi' => [
                $submittedUnionId && Union::whereKey($submittedUnionId)->where('division_id', $user->division_id)->exists()
                    ? $submittedUnionId
                    : null,
                $submittedConferenceId && Conference::whereKey($submittedConferenceId)->whereHas('union', fn ($q) => $q->where('division_id', $user->division_id))->exists()
                    ? $submittedConferenceId
                    : null,
            ],
            default => [$submittedUnionId ?? $currentUnionId, $submittedConferenceId ?? $currentConferenceId],
        };

        if ($conferenceId) {
            $unionId = Conference::whereKey($conferenceId)->value('union_id') ?? $unionId;
        }

        return [$unionId, $conferenceId];
    }

    /**
     * Nasional-level gets the full Uni → Daerah cascade (either left blank for a nasional
     * institution); admin_uni is pinned to their own Uni and only picks an optional Daerah
     * beneath it; admin_daerah is pinned to both, no picker at all — mirrors
     * ChurchController::conferencePickerData().
     */
    private function regionPickerData(Request $request): array
    {
        $user = $request->user();

        if ($user->role?->hasGlobalAccess()) {
            return [
                'canPickUnion' => true,
                'unions' => Union::where('is_active', true)->orderBy('name')->get(),
                'conferences' => Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'nasional') {
            $unionIds = $user->assignedUnionIds();

            return [
                'canPickUnion' => true,
                'unions' => Union::whereIn('id', $unionIds)->orderBy('name')->get(),
                'conferences' => Conference::whereIn('union_id', $unionIds)->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'divisi') {
            return [
                'canPickUnion' => true,
                'unions' => Union::where('division_id', $user->division_id)->where('is_active', true)->orderBy('name')->get(),
                'conferences' => Conference::whereHas('union', fn ($q) => $q->where('division_id', $user->division_id))->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'uni') {
            return [
                'canPickUnion' => false,
                'ownUnion' => Union::find($user->union_id),
                'unions' => collect(),
                'conferences' => Conference::where('union_id', $user->union_id)->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        return ['canPickUnion' => false, 'unions' => collect(), 'conferences' => collect()];
    }

    /**
     * No city AND country, no attempt — see ChurchController::applyGeocoding()'s doc comment.
     */
    private function applyGeocoding(array &$data, GeocodingService $geocoding): void
    {
        if (empty($data['city']) || empty($data['country'])) {
            return;
        }

        $result = $geocoding->geocode($geocoding->placeQueryFor($data['city'], $data['country']));

        if ($result) {
            $data['latitude'] = $result['lat'];
            $data['longitude'] = $result['lon'];
            $data['geocoded_at'] = now();
        }
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (Institution::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function toggleActive(Institution $institution): RedirectResponse
    {
        $institution->update(['is_active' => ! $institution->is_active]);
        $status = $institution->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $institution->is_active ? 'institution.activated' : 'institution.deactivated',
            $institution,
            ($institution->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Institusi \"{$institution->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])->with('status', __('accounts.entity_status_changed', ['entity' => __('common.institution'), 'name' => $institution->name, 'status' => $status]));
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * institution still has Users scoped to it (institution_id, restrictOnDelete), so an
     * unguarded delete() would just bubble up as a raw QueryException. Nonaktifkan is the
     * safe default; this is only for cleaning up an institution created by mistake.
     *
     * withTrashed() matters here: a soft-deleted User row (User uses SoftDeletes) is still
     * physically present and still trips the DB-level FK constraint even though Eloquent's
     * default query hides it.
     */
    public function destroy(Institution $institution): RedirectResponse
    {
        if ($institution->users()->withTrashed()->exists()) {
            return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])
                ->with('error', __('accounts.delete_blocked_institusi', ['name' => $institution->name]));
        }

        $name = $institution->name;
        $institution->delete();

        AuditLogger::log('institution.deleted', $institution, "Menghapus permanen Institusi \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])->with('status', __('accounts.entity_deleted', ['entity' => __('common.institution'), 'name' => $name]));
    }
}
