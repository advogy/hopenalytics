<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HashtagController extends Controller
{
    public function index()
    {
        $hashtags = Hashtag::withCount('posts')->orderByDesc('created_at')->get();

        return view('admin.hashtags.index', ['hashtags' => $hashtags]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Normalized before validation — strip a leading '#', trim, lowercase — so every
        // fetcher/query downstream can assume a plain, consistently-cased string, "#GMAHK" vs
        // "gmahk" can't sneak in as two visually-duplicate entries, and the unique rule below
        // actually catches a case-insensitive repeat.
        $request->merge(['tag' => Str::lower(ltrim(trim((string) $request->input('tag')), '#'))]);

        $data = $request->validate([
            'tag' => ['required', 'string', 'max:100', Rule::unique('hashtags', 'tag')],
        ], [
            'tag.unique' => 'Hashtag ini sudah dilacak.',
        ]);

        $hashtag = Hashtag::create([
            'tag' => $data['tag'],
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log('hashtag.created', $hashtag, "Menambahkan hashtag \"#{$hashtag->tag}\".");

        return redirect()->route('admin.hashtags.index')->with('status', "Hashtag \"#{$hashtag->tag}\" berhasil ditambahkan.");
    }

    public function toggleActive(Hashtag $hashtag): RedirectResponse
    {
        $hashtag->update(['is_active' => ! $hashtag->is_active]);
        $status = $hashtag->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        AuditLogger::log(
            $hashtag->is_active ? 'hashtag.activated' : 'hashtag.deactivated',
            $hashtag,
            ($hashtag->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." hashtag \"#{$hashtag->tag}\"."
        );

        return redirect()->route('admin.hashtags.index')->with('status', "Hashtag \"#{$hashtag->tag}\" telah {$status}.");
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Blocked whenever the
     * hashtag still has matched posts, so an unguarded delete() doesn't silently wipe
     * hashtag_posts history via the cascade — same "nonaktifkan is the safe default"
     * reasoning as UnionController::destroy() and its siblings.
     */
    public function destroy(Hashtag $hashtag): RedirectResponse
    {
        if ($hashtag->posts()->exists()) {
            return redirect()->route('admin.hashtags.index')
                ->with('error', "Hashtag \"#{$hashtag->tag}\" tidak bisa dihapus karena masih memiliki data post yang tersimpan. Nonaktifkan saja.");
        }

        $tag = $hashtag->tag;
        $hashtag->delete();

        AuditLogger::log('hashtag.deleted', $hashtag, "Menghapus permanen hashtag \"#{$tag}\".");

        return redirect()->route('admin.hashtags.index')->with('status', "Hashtag \"#{$tag}\" berhasil dihapus.");
    }
}
