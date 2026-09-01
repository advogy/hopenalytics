<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GroupPlatform;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Union;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UnionController extends Controller
{
    public function create(Request $request)
    {
        return view('admin.unions.form', ['union' => new Union] + $this->divisionPickerData($request));
    }

    /** Advisory "did you mean" lookup for the name field — see NameSimilarity. */
    public function similar(Request $request): JsonResponse
    {
        $matches = NameSimilarity::findSimilar(
            (string) $request->query('name', ''),
            Union::where('is_active', true)->when($request->query('exclude_id'), fn ($q, $id) => $q->whereKeyNot($id))->get(['id', 'slug', 'name']),
        );

        return response()->json($matches->map(fn ($m) => [
            'name' => $m['model']->name,
            'url' => route('admin.unions.edit', $m['model']),
        ])->values());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'coordinator_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'groups' => ['nullable', 'array'],
            'groups.*.platform' => ['nullable', Rule::enum(GroupPlatform::class)],
            'groups.*.url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['division_id'] = $this->resolveDivisionId($request, null);
        $groups = $data['groups'] ?? [];
        unset($data['groups']);

        $union = Union::create($data);
        $this->syncGroups($union, $groups);

        AuditLogger::log('union.created', $union, "Menambahkan Uni \"{$union->name}\".");

        // Straight to Kelola Akun Media Sosial rather than the accounts list — adding social
        // accounts is always the very next thing an admin does right after creating an entity,
        // per the user's explicit call (see ChurchController::store()).
        return redirect()->route('admin.unions.socials.index', $union)->with('status', __('accounts.entity_created', ['entity' => __('common.union'), 'name' => $data['name']]));
    }

    public function edit(Request $request, Union $union)
    {
        return view('admin.unions.form', ['union' => $union] + $this->divisionPickerData($request));
    }

    public function update(Request $request, Union $union): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'coordinator_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'groups' => ['nullable', 'array'],
            'groups.*.platform' => ['nullable', Rule::enum(GroupPlatform::class)],
            'groups.*.url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;
        $data['division_id'] = $this->resolveDivisionId($request, $union->division_id);
        $groups = $data['groups'] ?? [];
        unset($data['groups']);

        $union->update($data);
        $this->syncGroups($union, $groups);

        AuditLogger::log('union.updated', $union, "Memperbarui Uni \"{$union->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', __('accounts.entity_updated', ['entity' => __('common.union'), 'name' => $union->name]));
    }

    /**
     * A slim sibling of update() above, reached from Pengaturan → Koordinator Global rather
     * than Kelola Akun — lets a superadmin/admin_global set a Union's own coordinator contact
     * directly without touching its name/location, and without needing Union-edit access more
     * broadly (see the route's own can:manage-settings gate, narrower than update()'s
     * can:update,union — an admin_uni can still edit their own union's coordinator info via the
     * regular form, but only global-level actors reach it from here).
     */
    public function updateCoordinator(Request $request, Union $union): RedirectResponse
    {
        $data = $request->validate([
            'coordinator_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'groups' => ['nullable', 'array'],
            'groups.*.platform' => ['nullable', Rule::enum(GroupPlatform::class)],
            'groups.*.url' => ['nullable', 'url', 'max:2048'],
        ]);
        $groups = $data['groups'] ?? [];
        unset($data['groups']);

        $union->update($data);
        $this->syncGroups($union, $groups);

        AuditLogger::log('union.updated', $union, "Memperbarui kontak Koordinator Uni \"{$union->name}\" dari Pengaturan.");

        return redirect()->route('settings.edit', ['tab' => 'coordinator'])
            ->with('status', __('accounts.entity_updated', ['entity' => __('common.union'), 'name' => $union->name]));
    }

    public function toggleActive(Union $union): RedirectResponse
    {
        $union->update(['is_active' => ! $union->is_active]);
        $status = $union->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $union->is_active ? 'union.activated' : 'union.deactivated',
            $union,
            ($union->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Uni \"{$union->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', __('accounts.entity_status_changed', ['entity' => __('common.union'), 'name' => $union->name, 'status' => $status]));
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * union still has dependents: Daerah reference it with restrictOnDelete, and so do Users
     * scoped to it (union_id), so an unguarded delete() would just bubble up as a raw
     * QueryException. Nonaktifkan is the safe default; this is only for cleaning up a union
     * that was created by mistake and never actually used.
     *
     * withTrashed() matters here: a soft-deleted User row (User uses SoftDeletes) is still
     * physically present and still trips the DB-level FK constraint even though Eloquent's
     * default query hides it.
     */
    public function destroy(Union $union): RedirectResponse
    {
        if ($union->conferences()->exists() || $union->users()->withTrashed()->exists()) {
            return redirect()->route('admin.accounts.index', ['tab' => 'uni'])
                ->with('error', __('accounts.delete_blocked_uni', ['name' => $union->name]));
        }

        $name = $union->name;
        $union->delete();

        AuditLogger::log('union.deleted', $union, "Menghapus permanen Uni \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', __('accounts.entity_deleted', ['entity' => __('common.union'), 'name' => $name]));
    }

    /**
     * Never trust a client-submitted division assignment: an admin_divisi is always pinned to
     * their own division (see divisionPickerData() — the form only ever shows them a read-only
     * label, no picker), so any submitted division_id is ignored and their own is forced
     * instead. Every other role that can reach this form (global-level, and a scoped
     * admin_nasional — see UnionPolicy::update()) is trusted at face value, same as
     * ConferenceController::resolveUnionId()'s own default arm — a Division isn't part of Admin
     * Nasional's own Union-set scoping (see UserRole::level()'s doc comment), so there's no
     * narrower set to validate a nasional actor's submission against the way resolveUnionId()
     * does for its 'nasional' arm.
     */
    private function resolveDivisionId(Request $request, ?int $current): ?int
    {
        $user = $request->user();
        $submitted = $request->filled('division_id') ? (int) $request->input('division_id') : null;

        return match ($user->role->level()) {
            'divisi' => $user->division_id,
            default => $submitted ?? $current,
        };
    }

    /**
     * Global-level and a scoped admin_nasional get the full Divisi picker (nullable — a Union
     * doesn't have to belong to a Division yet); admin_divisi (the only other role that can
     * reach UnionPolicy::create()/update()) is already pinned to one division, so they see it
     * as read-only text instead — mirrors ConferenceController::unionPickerData() one tier up.
     */
    private function divisionPickerData(Request $request): array
    {
        $user = $request->user();

        if (($user->role?->hasGlobalAccess() ?? false) || $user->role?->level() === 'nasional') {
            return [
                'canPickDivision' => true,
                'divisions' => Division::where('is_active', true)->orderBy('name')->get(),
            ];
        }

        return ['canPickDivision' => false, 'divisions' => collect(), 'ownDivision' => Division::find($user->division_id)];
    }

    /** Replaces this Union's whole coordinator-group set — see SettingsController::update(). */
    private function syncGroups(Union $union, array $groups): void
    {
        $groups = collect($groups)->filter(fn ($g) => filled($g['url'] ?? null) && filled($g['platform'] ?? null));

        $union->groups()->delete();
        $groups->each(fn ($g) => $union->groups()->create(['platform' => $g['platform'], 'url' => $g['url']]));
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (Union::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
