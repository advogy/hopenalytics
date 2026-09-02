<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Support\AuditLogger;
use App\Support\HashtagRescanDispatcher;
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
            'tag.unique' => __('hashtag.already_tracked'),
        ]);

        $hashtag = Hashtag::create([
            'tag' => $data['tag'],
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log('hashtag.created', $hashtag, "Menambahkan hashtag \"#{$hashtag->tag}\".");

        return redirect()->route('admin.hashtags.index')->with('status', __('hashtag.created', ['tag' => $hashtag->tag]));
    }

    /**
     * On-demand "scan every registered account right now" trigger — per the user's explicit
     * call for a flexible way to get fresher hashtag data around a specific event (e.g.
     * monitoring a coordinated hashtag launch hour by hour) without waiting for the once-a-week
     * auto-fetch, and without needing SSH access to run `hashtags:rescan` directly. Costs real
     * Apify credits/YouTube quota per account scanned (see HashtagRescanDispatcher's own doc
     * comment) — the confirm dialog on the button itself says so.
     */
    public function rescan(): RedirectResponse
    {
        if (! Hashtag::where('is_active', true)->exists()) {
            return redirect()->route('admin.hashtags.index')->with('error', __('hashtag.rescan_no_active_hashtags'));
        }

        $total = HashtagRescanDispatcher::dispatch();

        AuditLogger::log('hashtag.rescan-triggered', null, "Memicu scan ulang {$total} akun untuk hashtag yang dilacak.");

        return redirect()->route('admin.hashtags.index')->with('status', __('hashtag.rescan_started', ['count' => $total]));
    }

    public function toggleActive(Hashtag $hashtag): RedirectResponse
    {
        $hashtag->update(['is_active' => ! $hashtag->is_active]);

        AuditLogger::log(
            $hashtag->is_active ? 'hashtag.activated' : 'hashtag.deactivated',
            $hashtag,
            ($hashtag->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." hashtag \"#{$hashtag->tag}\"."
        );

        $message = $hashtag->is_active
            ? __('hashtag.reactivated_message', ['tag' => $hashtag->tag])
            : __('hashtag.deactivated_message', ['tag' => $hashtag->tag]);

        return redirect()->route('admin.hashtags.index')->with('status', $message);
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
                ->with('error', __('hashtag.delete_blocked', ['tag' => $hashtag->tag]));
        }

        $tag = $hashtag->tag;
        $hashtag->delete();

        AuditLogger::log('hashtag.deleted', $hashtag, "Menghapus permanen hashtag \"#{$tag}\".");

        return redirect()->route('admin.hashtags.index')->with('status', __('hashtag.deleted', ['tag' => $tag]));
    }
}
