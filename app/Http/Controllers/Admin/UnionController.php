<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Union;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UnionController extends Controller
{
    public function create()
    {
        return view('admin.unions.form', ['union' => new Union]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $union = Union::create($data);

        AuditLogger::log('union.created', $union, "Menambahkan Uni \"{$union->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', "Uni \"{$data['name']}\" berhasil ditambahkan.");
    }

    public function edit(Union $union)
    {
        return view('admin.unions.form', ['union' => $union]);
    }

    public function update(Request $request, Union $union): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $union->update($data);

        AuditLogger::log('union.updated', $union, "Memperbarui Uni \"{$union->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', "Uni \"{$union->name}\" berhasil diperbarui.");
    }

    public function toggleActive(Union $union): RedirectResponse
    {
        $union->update(['is_active' => ! $union->is_active]);
        $status = $union->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        AuditLogger::log(
            $union->is_active ? 'union.activated' : 'union.deactivated',
            $union,
            ($union->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Uni \"{$union->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', "Uni \"{$union->name}\" telah {$status}.");
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
                ->with('error', "Uni \"{$union->name}\" tidak bisa dihapus karena masih memiliki Daerah dan/atau pengguna yang ditugaskan. Nonaktifkan saja, atau pindahkan/hapus data terkait terlebih dahulu.");
        }

        $name = $union->name;
        $union->delete();

        AuditLogger::log('union.deleted', $union, "Menghapus permanen Uni \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'uni'])->with('status', "Uni \"{$name}\" berhasil dihapus.");
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
