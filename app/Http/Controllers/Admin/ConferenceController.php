<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConferenceController extends Controller
{
    public function create()
    {
        return view('admin.conferences.form', [
            'conference' => new Conference,
            'unions' => Union::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'union_id' => ['required', 'integer', 'exists:unions,id'],
        ]);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Conference::create($data);

        return redirect()->route('admin.hierarchy.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$data['name']}\" berhasil ditambahkan.");
    }

    public function edit(Conference $conference)
    {
        return view('admin.conferences.form', [
            'conference' => $conference,
            'unions' => Union::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Conference $conference): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'union_id' => ['required', 'integer', 'exists:unions,id'],
        ]);

        $conference->update($data);

        return redirect()->route('admin.hierarchy.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$conference->name}\" berhasil diperbarui.");
    }

    public function toggleActive(Conference $conference): RedirectResponse
    {
        $conference->update(['is_active' => ! $conference->is_active]);
        $status = $conference->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        return redirect()->route('admin.hierarchy.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$conference->name}\" telah {$status}.");
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * conference still has dependents: Gereja and Users scoped to it (conference_id) both
     * reference it, so an unguarded delete() would either silently orphan churches or bubble
     * up as a raw QueryException. Nonaktifkan is the safe default; this is only for cleaning
     * up a conference that was created by mistake and never actually used.
     */
    public function destroy(Conference $conference): RedirectResponse
    {
        if ($conference->churches()->exists() || $conference->users()->exists()) {
            return redirect()->route('admin.hierarchy.index', ['tab' => 'daerah'])
                ->with('error', "Daerah \"{$conference->name}\" tidak bisa dihapus karena masih memiliki Gereja dan/atau pengguna yang ditugaskan. Nonaktifkan saja, atau pindahkan/hapus data terkait terlebih dahulu.");
        }

        $name = $conference->name;
        $conference->delete();

        return redirect()->route('admin.hierarchy.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$name}\" berhasil dihapus.");
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
