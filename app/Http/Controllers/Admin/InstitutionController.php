<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstitutionController extends Controller
{
    public function create()
    {
        return view('admin.institutions.form', ['institution' => new Institution]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Institution::create($data);

        return redirect()->route('admin.hierarchy.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$data['name']}\" berhasil ditambahkan.");
    }

    public function edit(Institution $institution)
    {
        return view('admin.institutions.form', ['institution' => $institution]);
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $institution->update($data);

        return redirect()->route('admin.hierarchy.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$institution->name}\" berhasil diperbarui.");
    }

    public function toggleActive(Institution $institution): RedirectResponse
    {
        $institution->update(['is_active' => ! $institution->is_active]);
        $status = $institution->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        return redirect()->route('admin.hierarchy.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$institution->name}\" telah {$status}.");
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * institution still has Users scoped to it (institution_id, restrictOnDelete), so an
     * unguarded delete() would just bubble up as a raw QueryException. Nonaktifkan is the
     * safe default; this is only for cleaning up an institution created by mistake.
     */
    public function destroy(Institution $institution): RedirectResponse
    {
        if ($institution->users()->exists()) {
            return redirect()->route('admin.hierarchy.index', ['tab' => 'institusi'])
                ->with('error', "Institusi \"{$institution->name}\" tidak bisa dihapus karena masih ada pengguna yang ditugaskan. Nonaktifkan saja, atau pindahkan penugasan pengguna terlebih dahulu.");
        }

        $name = $institution->name;
        $institution->delete();

        return redirect()->route('admin.hierarchy.index', ['tab' => 'institusi'])->with('status', "Institusi \"{$name}\" berhasil dihapus.");
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
}
