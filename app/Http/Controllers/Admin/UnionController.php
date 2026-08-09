<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Union;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UnionController extends Controller
{
    public function create()
    {
        return view('admin.unions.form', ['union' => new Union]);
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
            'coordinator_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'whatsapp_group_link' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;
        $data['slug'] = $this->uniqueSlug($data['name']);

        $union = Union::create($data);

        AuditLogger::log('union.created', $union, "Menambahkan Uni \"{$union->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', __('accounts.entity_created', ['entity' => __('common.union'), 'name' => $data['name']]));
    }

    public function edit(Union $union)
    {
        return view('admin.unions.form', ['union' => $union]);
    }

    public function update(Request $request, Union $union): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'coordinator_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'whatsapp_group_link' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $union->update($data);

        AuditLogger::log('union.updated', $union, "Memperbarui Uni \"{$union->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', __('accounts.entity_updated', ['entity' => __('common.union'), 'name' => $union->name]));
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
