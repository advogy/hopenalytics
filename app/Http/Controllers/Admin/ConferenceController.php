<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Union;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConferenceController extends Controller
{
    public function create(Request $request)
    {
        return view('admin.conferences.form', ['conference' => new Conference] + $this->unionPickerData($request));
    }

    /** Advisory "did you mean" lookup for the name field — see NameSimilarity. */
    public function similar(Request $request): JsonResponse
    {
        $matches = NameSimilarity::findSimilar(
            (string) $request->query('name', ''),
            Conference::where('is_active', true)
                ->when($request->query('exclude_id'), fn ($q, $id) => $q->whereKeyNot($id))
                ->with('union')
                ->get(['id', 'slug', 'name', 'union_id']),
        );

        return response()->json($matches->map(fn ($m) => [
            'name' => $m['model']->name,
            'context' => $m['model']->union?->name,
            'url' => route('admin.conferences.edit', $m['model']),
        ])->values());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'union_id' => ['required', 'integer', 'exists:unions,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['union_id'] = $this->resolveUnionId($request, $data['union_id']);

        $conference = Conference::create($data);

        AuditLogger::log('conference.created', $conference, "Menambahkan Daerah \"{$conference->name}\".");

        // Straight to Kelola Akun Media Sosial rather than the accounts list — adding social
        // accounts is always the very next thing an admin does right after creating an entity,
        // per the user's explicit call (see ChurchController::store()).
        return redirect()->route('admin.conferences.socials.index', $conference)->with('status', __('accounts.entity_created', ['entity' => __('common.conference'), 'name' => $data['name']]));
    }

    public function edit(Request $request, Conference $conference)
    {
        return view('admin.conferences.form', ['conference' => $conference] + $this->unionPickerData($request));
    }

    public function update(Request $request, Conference $conference): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'union_id' => ['required', 'integer', 'exists:unions,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;
        $data['union_id'] = $this->resolveUnionId($request, $conference->union_id);

        $conference->update($data);

        AuditLogger::log('conference.updated', $conference, "Memperbarui Daerah \"{$conference->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])->with('status', __('accounts.entity_updated', ['entity' => __('common.conference'), 'name' => $conference->name]));
    }

    /**
     * Never trust a client-submitted union assignment: an admin_uni is always pinned to their
     * own union (see unionPickerData() — the form only ever shows them a read-only label, no
     * picker), so any submitted union_id is ignored and their own is forced instead. A scoped
     * admin_nasional's submission is re-validated against their own assigned Union set. Global-
     * level (superadmin/admin_global) roles reach here with $submitted taken at face value
     * (already validated against exists:unions,id above).
     */
    private function resolveUnionId(Request $request, ?int $current): ?int
    {
        $user = $request->user();
        $submitted = $request->filled('union_id') ? (int) $request->input('union_id') : null;

        return match ($user->role->level()) {
            'uni' => $user->union_id,
            'nasional' => $submitted && in_array($submitted, $user->assignedUnionIds(), true) ? $submitted : $current,
            'divisi' => $submitted && Union::whereKey($submitted)->where('division_id', $user->division_id)->exists() ? $submitted : $current,
            default => $submitted ?? $current,
        };
    }

    /**
     * Global-level gets the full Uni picker; a scoped admin_nasional gets a picker limited to
     * their own assigned Unions; admin_uni (the only other role that can reach
     * ConferencePolicy::create()) is already pinned to one union, so they see it as read-only
     * text instead — mirrors ChurchController::conferencePickerData() for the Gereja tab.
     */
    private function unionPickerData(Request $request): array
    {
        $user = $request->user();

        if ($user->role?->hasGlobalAccess()) {
            return [
                'canPickUnion' => true,
                'unions' => Union::where('is_active', true)->orderBy('name')->get(),
            ];
        }

        if ($user->role?->level() === 'nasional') {
            return [
                'canPickUnion' => true,
                'unions' => Union::whereIn('id', $user->assignedUnionIds())->where('is_active', true)->orderBy('name')->get(),
            ];
        }

        if ($user->role?->level() === 'divisi') {
            return [
                'canPickUnion' => true,
                'unions' => Union::where('division_id', $user->division_id)->where('is_active', true)->orderBy('name')->get(),
            ];
        }

        return ['canPickUnion' => false, 'unions' => collect(), 'ownUnion' => Union::find($user->union_id)];
    }

    public function toggleActive(Conference $conference): RedirectResponse
    {
        $conference->update(['is_active' => ! $conference->is_active]);
        $status = $conference->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $conference->is_active ? 'conference.activated' : 'conference.deactivated',
            $conference,
            ($conference->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Daerah \"{$conference->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])->with('status', __('accounts.entity_status_changed', ['entity' => __('common.conference'), 'name' => $conference->name, 'status' => $status]));
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * conference still has dependents: Gereja and Users scoped to it (conference_id) both
     * reference it, so an unguarded delete() would either silently orphan churches or bubble
     * up as a raw QueryException. Nonaktifkan is the safe default; this is only for cleaning
     * up a conference that was created by mistake and never actually used.
     *
     * withTrashed() matters here: a soft-deleted User row (User uses SoftDeletes) is still
     * physically present and still trips the DB-level FK constraint even though Eloquent's
     * default query hides it.
     */
    public function destroy(Conference $conference): RedirectResponse
    {
        if ($conference->churches()->exists() || $conference->users()->withTrashed()->exists()) {
            return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])
                ->with('error', __('accounts.delete_blocked_daerah', ['name' => $conference->name]));
        }

        $name = $conference->name;
        $conference->delete();

        AuditLogger::log('conference.deleted', $conference, "Menghapus permanen Daerah \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])->with('status', __('accounts.entity_deleted', ['entity' => __('common.conference'), 'name' => $name]));
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (Conference::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
