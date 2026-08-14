<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DivisionController extends Controller
{
    public function create()
    {
        return view('admin.divisions.form', ['division' => new Division]);
    }

    /** Advisory "did you mean" lookup for the name field — see NameSimilarity. */
    public function similar(Request $request): JsonResponse
    {
        $matches = NameSimilarity::findSimilar(
            (string) $request->query('name', ''),
            Division::where('is_active', true)->when($request->query('exclude_id'), fn ($q, $id) => $q->whereKeyNot($id))->get(['id', 'slug', 'name']),
        );

        return response()->json($matches->map(fn ($m) => [
            'name' => $m['model']->name,
            'url' => route('admin.divisions.edit', $m['model']),
        ])->values());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $division = Division::create($data);

        AuditLogger::log('division.created', $division, "Menambahkan Divisi \"{$division->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'divisi'])->with('status', __('accounts.entity_created', ['entity' => __('common.division'), 'name' => $data['name']]));
    }

    public function edit(Division $division)
    {
        return view('admin.divisions.form', ['division' => $division]);
    }

    public function update(Request $request, Division $division): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $division->update($data);

        AuditLogger::log('division.updated', $division, "Memperbarui Divisi \"{$division->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'divisi'])->with('status', __('accounts.entity_updated', ['entity' => __('common.division'), 'name' => $division->name]));
    }

    public function toggleActive(Division $division): RedirectResponse
    {
        $division->update(['is_active' => ! $division->is_active]);
        $status = $division->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $division->is_active ? 'division.activated' : 'division.deactivated',
            $division,
            ($division->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Divisi \"{$division->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'divisi'])->with('status', __('accounts.entity_status_changed', ['entity' => __('common.division'), 'name' => $division->name, 'status' => $status]));
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * division still has dependents: Union references it with restrictOnDelete, and so do
     * Users scoped to it (division_id), so an unguarded delete() would just bubble up as a raw
     * QueryException. Nonaktifkan is the safe default; this is only for cleaning up a division
     * that was created by mistake and never actually used.
     *
     * withTrashed() matters here: a soft-deleted User row (User uses SoftDeletes) is still
     * physically present and still trips the DB-level FK constraint even though Eloquent's
     * default query hides it.
     */
    public function destroy(Division $division): RedirectResponse
    {
        if ($division->unions()->exists() || $division->users()->withTrashed()->exists()) {
            return redirect()->route('admin.accounts.index', ['tab' => 'divisi'])
                ->with('error', __('accounts.delete_blocked_divisi', ['name' => $division->name]));
        }

        $name = $division->name;
        $division->delete();

        AuditLogger::log('division.deleted', $division, "Menghapus permanen Divisi \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'divisi'])->with('status', __('accounts.entity_deleted', ['entity' => __('common.division'), 'name' => $name]));
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (Division::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
