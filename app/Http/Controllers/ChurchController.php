<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Conference;
use App\Models\Union;
use App\Services\GeocodingService;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChurchController extends Controller
{
    public function create(Request $request)
    {
        return view('churches.form', ['church' => new Church] + $this->conferencePickerData($request));
    }

    /**
     * Advisory "did you mean" lookup for the name field — see NameSimilarity. Deliberately not
     * gated behind can:create,Church (unlike the create/store routes) since an admin_gereja can
     * update their own church's name — see ChurchPolicy — but is barred from create(); the
     * check needs to work on both the create AND edit forms, and church names are already fully
     * public via the Directory page, so no extra authorization narrows this beyond plain auth.
     */
    public function similar(Request $request): JsonResponse
    {
        $matches = NameSimilarity::findSimilar(
            (string) $request->query('name', ''),
            Church::where('is_active', true)
                ->when($request->query('exclude_id'), fn ($q, $id) => $q->whereKeyNot($id))
                ->with('conference.union')
                ->get(['id', 'slug', 'name', 'conference_id']),
        );

        return response()->json($matches->map(fn ($m) => [
            'name' => $m['model']->name,
            'context' => $m['model']->conference?->name,
            'url' => route('churches.edit', $m['model']),
        ])->values());
    }

    public function store(Request $request, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $data['conference_id'] = $this->resolveConferenceId($request, null);

        if ($data['latitude'] !== null && $data['longitude'] !== null) {
            $data['geocoded_at'] = null;
        } else {
            $this->applyGeocoding($data, $geocoding);
        }

        $church = $this->createChurchWithUniqueSlug($data);

        AuditLogger::log('church.created', $church, "Menambahkan Gereja \"{$church->name}\".");

        return redirect()->route('churches.show', $church)->with('status', __('accounts.entity_created', ['entity' => __('common.church'), 'name' => $church->name]));
    }

    public function edit(Request $request, Church $church)
    {
        return view('churches.form', ['church' => $church] + $this->conferencePickerData($request));
    }

    public function update(Request $request, Church $church, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $coordsManuallyChanged = $data['latitude'] !== $church->latitude || $data['longitude'] !== $church->longitude;

        if ($coordsManuallyChanged) {
            $data['geocoded_at'] = null;
        } elseif ($data['city'] !== $church->city) {
            $this->applyGeocoding($data, $geocoding);
        }

        $data['conference_id'] = $this->resolveConferenceId($request, $church->conference_id);

        $church->update($data);

        AuditLogger::log('church.updated', $church, "Memperbarui Gereja \"{$church->name}\".");

        // churches.edit is only ever reached from Kelola Akun (see admin.accounts.partials.
        // row-actions) — same destination as this form's own Back/Cancel links, so a successful
        // save lands back where the admin actually came from instead of the public show page.
        return redirect()->route('admin.accounts.index', ['tab' => 'gereja'])->with('status', __('accounts.entity_updated', ['entity' => __('common.church'), 'name' => $church->name]));
    }

    /**
     * Never trust a client-submitted org assignment: an admin_daerah is always pinned to
     * their own conference; an admin_uni may only assign a conference within their own union
     * (see conferencePickerData() — the form only ever shows one of those two a picker
     * scoped to what they're allowed to submit); nasional-level roles are trusted as-is.
     */
    private function resolveConferenceId(Request $request, ?int $current): ?int
    {
        $user = $request->user();
        $submitted = $request->filled('conference_id') ? (int) $request->input('conference_id') : null;

        return match ($user->role->level()) {
            'daerah' => $user->conference_id,
            'nasional' => $submitted && Conference::whereKey($submitted)->whereIn('union_id', $user->assignedUnionIds())->exists()
                ? $submitted
                : $current,
            'divisi' => $submitted && Conference::whereKey($submitted)->whereHas('union', fn ($q) => $q->where('division_id', $user->division_id))->exists()
                ? $submitted
                : $current,
            'uni' => $submitted && Conference::whereKey($submitted)->where('union_id', $user->union_id)->exists()
                ? $submitted
                : $current,
            default => $submitted ?? $current,
        };
    }

    /**
     * Which Daerah picker (if any) the form shows depends on the actor's own level, mirroring
     * resolveConferenceId() above: nasional-level gets the full Uni → Daerah cascade,
     * admin_uni gets a flat Daerah list scoped to their own union, everyone else (admin_daerah
     * is always pinned to their own; admin_gereja/pimpinan aren't meant to move a church's
     * parent structure at all) sees no picker — just the current Daerah as read-only text.
     */
    private function conferencePickerData(Request $request): array
    {
        $user = $request->user();

        if ($user->role?->hasGlobalAccess()) {
            return [
                'canPickConference' => true,
                'unions' => Union::where('is_active', true)->orderBy('name')->get(),
                'conferences' => Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'nasional') {
            $unionIds = $user->assignedUnionIds();

            return [
                'canPickConference' => true,
                'unions' => Union::whereIn('id', $unionIds)->orderBy('name')->get(),
                'conferences' => Conference::whereIn('union_id', $unionIds)->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'divisi') {
            return [
                'canPickConference' => true,
                'unions' => Union::where('division_id', $user->division_id)->orderBy('name')->get(),
                'conferences' => Conference::whereHas('union', fn ($q) => $q->where('division_id', $user->division_id))->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'uni') {
            return [
                'canPickConference' => true,
                'unions' => collect(),
                'conferences' => Conference::where('union_id', $user->union_id)->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        return ['canPickConference' => false, 'unions' => collect(), 'conferences' => collect()];
    }

    /**
     * The obvious "check exists(), then create()" approach has a race: two near-simultaneous
     * submissions for the same church name can both pass the exists() check before either
     * commits, so the second create() hits a raw UniqueConstraintViolationException on
     * churches_slug_unique instead of a clean error (confirmed happening in production).
     * Retrying with a re-derived slug after a collision closes that window.
     */
    private function createChurchWithUniqueSlug(array $data): Church
    {
        $original = Str::slug($data['name']);
        $slug = $original;
        $i = 1;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            while (Church::where('slug', $slug)->exists()) {
                $slug = "{$original}-{$i}";
                $i++;
            }

            try {
                return Church::create(['slug' => $slug] + $data);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $slug = "{$original}-{$i}";
                $i++;
            }
        }

        throw new \RuntimeException('Could not generate a unique church slug after 5 attempts.');
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

    public function toggleActive(Church $church): RedirectResponse
    {
        $church->update(['is_active' => ! $church->is_active]);
        $status = $church->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $church->is_active ? 'church.activated' : 'church.deactivated',
            $church,
            ($church->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Gereja \"{$church->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'gereja'])->with('status', __('accounts.entity_status_changed', ['entity' => __('common.church'), 'name' => $church->name, 'status' => $status]));
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * church still has Users scoped to it (church_id, restrictOnDelete), so an unguarded
     * delete() would just bubble up as a raw QueryException. Its ChurchSocial accounts cascade
     * delete along with it — that's intentional cleanup, not something to guard against.
     * Nonaktifkan is the safe default; this is only for cleaning up a church created by mistake.
     *
     * withTrashed() matters here: a soft-deleted User row (User uses SoftDeletes) is still
     * physically present in the table and still trips the DB-level FK constraint, even though
     * Eloquent's default query hides it — without withTrashed() this check would say "no users"
     * and then fail with a raw QueryException on the delete() below anyway.
     */
    public function destroy(Church $church): RedirectResponse
    {
        if ($church->users()->withTrashed()->exists()) {
            return redirect()->route('admin.accounts.index', ['tab' => 'gereja'])
                ->with('error', __('accounts.delete_blocked_gereja', ['name' => $church->name]));
        }

        $name = $church->name;
        $church->delete();

        AuditLogger::log('church.deleted', $church, "Menghapus permanen Gereja \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'gereja'])->with('status', __('accounts.entity_deleted', ['entity' => __('common.church'), 'name' => $name]));
    }
}
