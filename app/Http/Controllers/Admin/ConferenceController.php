<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Union;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConferenceController extends Controller
{
    public function create(Request $request)
    {
        return view('admin.conferences.form', ['conference' => new Conference] + $this->unionPickerData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'union_id' => ['required', 'integer', 'exists:unions,id'],
        ]);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['union_id'] = $this->resolveUnionId($request, $data['union_id']);

        $conference = Conference::create($data);

        AuditLogger::log('conference.created', $conference, "Menambahkan Daerah \"{$conference->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$data['name']}\" berhasil ditambahkan.");
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
        ]);
        $data['union_id'] = $this->resolveUnionId($request, $conference->union_id);

        $conference->update($data);

        AuditLogger::log('conference.updated', $conference, "Memperbarui Daerah \"{$conference->name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$conference->name}\" berhasil diperbarui.");
    }

    /**
     * Never trust a client-submitted union assignment: an admin_uni is always pinned to their
     * own union (see unionPickerData() — the form only ever shows them a read-only label, no
     * picker), so any submitted union_id is ignored and their own is forced instead. Only
     * nasional-level roles reach here with $submitted actually taken at face value (already
     * validated against exists:unions,id above).
     */
    private function resolveUnionId(Request $request, ?int $current): ?int
    {
        $user = $request->user();
        $submitted = $request->filled('union_id') ? (int) $request->input('union_id') : null;

        return $user->role->level() === 'uni' ? $user->union_id : ($submitted ?? $current);
    }

    /**
     * Nasional-level gets the full Uni picker; admin_uni (the only other role that can reach
     * ConferencePolicy::create()) is already pinned to one union, so they see it as read-only
     * text instead — mirrors ChurchController::conferencePickerData() for the Gereja tab.
     */
    private function unionPickerData(Request $request): array
    {
        $user = $request->user();

        if ($user->role?->hasNasionalAccess()) {
            return [
                'canPickUnion' => true,
                'unions' => Union::where('is_active', true)->orderBy('name')->get(),
            ];
        }

        return ['canPickUnion' => false, 'unions' => collect(), 'ownUnion' => Union::find($user->union_id)];
    }

    public function toggleActive(Conference $conference): RedirectResponse
    {
        $conference->update(['is_active' => ! $conference->is_active]);
        $status = $conference->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        AuditLogger::log(
            $conference->is_active ? 'conference.activated' : 'conference.deactivated',
            $conference,
            ($conference->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Daerah \"{$conference->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$conference->name}\" telah {$status}.");
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
                ->with('error', "Daerah \"{$conference->name}\" tidak bisa dihapus karena masih memiliki Gereja dan/atau pengguna yang ditugaskan. Nonaktifkan saja, atau pindahkan/hapus data terkait terlebih dahulu.");
        }

        $name = $conference->name;
        $conference->delete();

        AuditLogger::log('conference.deleted', $conference, "Menghapus permanen Daerah \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'daerah'])->with('status', "Daerah \"{$name}\" berhasil dihapus.");
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
