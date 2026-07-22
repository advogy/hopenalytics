<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Union;
use App\Services\GeocodingService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstitutionController extends Controller
{
    public function create(Request $request)
    {
        return view('admin.institutions.form', ['institution' => new Institution] + $this->regionPickerData($request));
    }

    public function store(Request $request, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
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

        return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$institution->name}\" berhasil ditambahkan.");
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $coordsManuallyChanged = $data['latitude'] !== $institution->latitude || $data['longitude'] !== $institution->longitude;

        if ($coordsManuallyChanged) {
            $data['geocoded_at'] = null;
        } elseif ($data['city'] !== $institution->city) {
            $this->applyGeocoding($data, $geocoding);
        }

        [$data['union_id'], $data['conference_id']] = $this->resolveRegion($request, $institution->union_id, $institution->conference_id);

        $institution->update($data);

        AuditLogger::log('institution.updated', $institution, "Memperbarui Institusi \"{$institution->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$institution->name}\" berhasil diperbarui.");
    }

    /**
     * Never trust a client-submitted region assignment: an admin_daerah is always pinned to
     * their own Uni+Daerah; an admin_uni is pinned to their own Uni, only the Daerah-under-it
     * picker (regionPickerData() below) is a real choice for them, and it's re-validated here
     * against their own union regardless; nasional-level is trusted as-is, including leaving
     * both blank for a nasional institution. Whatever conference ends up selected always forces
     * its own union_id too, so the denormalized invariant (Institution::scopeVisibleTo()) never
     * drifts even if a nasional actor's picker submits a stale/omitted union_id.
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

        if ($user->role?->hasNasionalAccess()) {
            return [
                'canPickUnion' => true,
                'unions' => Union::where('is_active', true)->orderBy('name')->get(),
                'conferences' => Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
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

    private function applyGeocoding(array &$data, GeocodingService $geocoding): void
    {
        $query = $geocoding->placeQueryFor($data['city'] ?? null, $data['name']);
        $result = $geocoding->geocode($query);

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
        $status = $institution->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        AuditLogger::log(
            $institution->is_active ? 'institution.activated' : 'institution.deactivated',
            $institution,
            ($institution->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Institusi \"{$institution->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$institution->name}\" telah {$status}.");
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
                ->with('error', "Institusi \"{$institution->name}\" tidak bisa dihapus karena masih ada pengguna yang ditugaskan. Nonaktifkan saja, atau pindahkan penugasan pengguna terlebih dahulu.");
        }

        $name = $institution->name;
        $institution->delete();

        AuditLogger::log('institution.deleted', $institution, "Menghapus permanen Institusi \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$name}\" berhasil dihapus.");
    }
}
