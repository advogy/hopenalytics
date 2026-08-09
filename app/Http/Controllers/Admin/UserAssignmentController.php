<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Union;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserAssignmentController extends Controller
{
    /**
     * A scoped "manage people under me" page — the level an actor may promote into is
     * always exactly one below their own (decision #5), so the whole page is built around
     * that single target level. Institusi is the one exception: it sits outside that chain
     * (see UserRole::level()), so its roles are merged into the same role/scope dropdown for
     * any nasional-level actor rather than getting a separate assignment flow. Superadmin AND
     * admin_nasional get a further exception (bootstrap capability): they may assign/review
     * uni/daerah/gereja directly, since no Admin Uni/Daerah needs to exist yet to seed one —
     * admin_nasional just can't bootstrap into nasional itself (see UserPolicy::promote()).
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        $targetLevel = $actor->role?->promotesToLevel();
        $canManageInstitutions = $actor->role?->hasNasionalAccess() ?? false;
        $isSuperAdmin = $actor->role === UserRole::SuperAdmin;
        $canBootstrapAnyLevel = $isSuperAdmin || $actor->role === UserRole::AdminNasional;
        $bootstrapLevels = $isSuperAdmin ? ['nasional', 'uni', 'daerah', 'gereja'] : ['uni', 'daerah', 'gereja'];

        abort_if($targetLevel === null, 403);

        $search = trim((string) $request->query('search'));

        // Nasional-level actors (superadmin/admin_nasional) see everyone — there's no
        // narrower "their own region" to filter by. Admin Uni/Daerah only see members who
        // self-reported (or were previously assigned) a union/conference matching their own,
        // via the "Lengkapi Profil" step (see CompleteProfileController) — anyone who skipped
        // it stays nasional-only, invisible to regional admins until they fill it in.
        $unassigned = User::query()
            ->whereNull('role')
            ->when($targetLevel === 'daerah', fn ($q) => $q->where('union_id', $actor->union_id))
            ->when($targetLevel === 'gereja', fn ($q) => $q->where('conference_id', $actor->conference_id))
            ->when($search, fn ($q) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        if ($canBootstrapAnyLevel) {
            $adminRoleValues = array_map(fn ($level) => $this->adminRoleForLevel($level)->value, $bootstrapLevels);
            $pimpinanRoleValues = array_map(fn ($level) => $this->pimpinanRoleForLevel($level)->value, $bootstrapLevels);

            $scopeRelations = ['union', 'conference.union', 'church.conference.union'];
            $adminUsers = User::whereIn('role', $adminRoleValues)->with($scopeRelations)->orderBy('name')->get();
            $pimpinanUsers = User::whereIn('role', $pimpinanRoleValues)->with($scopeRelations)->orderBy('name')->get();
        } else {
            $adminRole = $this->adminRoleForLevel($targetLevel);
            $pimpinanRole = $this->pimpinanRoleForLevel($targetLevel);

            $adminUsers = $this->assignedUsersQuery($actor, $targetLevel, [$adminRole->value])->orderBy('name')->get();
            $pimpinanUsers = $this->assignedUsersQuery($actor, $targetLevel, [$pimpinanRole->value])->orderBy('name')->get();
        }

        $institutionAdmins = $canManageInstitutions
            ? User::where('role', UserRole::AdminInstitusi->value)->with('institution')->orderBy('name')->get()
            : collect();
        $institutionPimpinan = $canManageInstitutions
            ? User::where('role', UserRole::PimpinanInstitusi->value)->with('institution')->orderBy('name')->get()
            : collect();
        $institutionOptions = $canManageInstitutions
            ? Institution::where('is_active', true)->orderBy('name')->get()
            : collect();

        // The role dropdown on "Belum Ditugaskan" and the scope options it reveals per role
        // (see resources/views/partials/region-fields.blade.php's sibling assign form) —
        // merged here so institusi is just two more options rather than a separate flow.
        $roles = $canBootstrapAnyLevel
            ? collect($bootstrapLevels)->flatMap(fn ($level) => [$this->adminRoleForLevel($level), $this->pimpinanRoleForLevel($level)])->all()
            : $this->rolesForLevel($targetLevel);

        $scopeDataByLevel = [];

        if ($canBootstrapAnyLevel) {
            $scopeDataByLevel['uni'] = Union::where('is_active', true)->orderBy('name')->get()
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values();
            $scopeDataByLevel['daerah'] = Conference::with('union')->where('is_active', true)->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->name} ({$c->union->name})"])->values();
            $scopeDataByLevel['gereja'] = Church::with('conference')->where('is_active', true)->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->name} ({$c->conference?->name})"])->values();
        } else {
            $scopeOptions = $this->scopeOptions($actor, $targetLevel);
            if ($scopeOptions->isNotEmpty()) {
                $scopeDataByLevel[$targetLevel] = $scopeOptions->map(fn ($o) => ['id' => $o->id, 'label' => $o->name])->values();
            }
        }

        if ($canManageInstitutions) {
            $roles = array_merge($roles, [UserRole::AdminInstitusi, UserRole::PimpinanInstitusi]);
            $scopeDataByLevel['institusi'] = $institutionOptions->map(fn ($i) => ['id' => $i->id, 'label' => $i->name])->values();
        }

        $activeTab = in_array($request->query('tab'), ['admin', 'pemimpin', 'institusi', 'terhapus'], true) ? $request->query('tab') : 'unassigned';

        // Soft-deleted (destroy()'d) users still physically exist and can silently block a
        // restrictOnDelete FK elsewhere (Union/Conference/Church/Institution) — this is where
        // Superadmin reaches in to either restore one or purge it for good (manage-deleted-users
        // gate, see AppServiceProvider). Everyone else never sees this tab at all.
        $trashedUsers = $isSuperAdmin
            ? User::onlyTrashed()->with(['union', 'conference.union', 'church.conference.union', 'institution'])->orderByDesc('deleted_at')->get()
            : collect();

        return view('admin.users.index', [
            'targetLevel' => $targetLevel,
            'activeTab' => $activeTab,
            'search' => $search,
            'unassigned' => $unassigned,
            'adminUsers' => $adminUsers,
            'pimpinanUsers' => $pimpinanUsers,
            'roles' => $roles,
            'scopeDataByLevel' => $scopeDataByLevel,
            'canManageInstitutions' => $canManageInstitutions,
            'institutionAdmins' => $institutionAdmins,
            'institutionPimpinan' => $institutionPimpinan,
            'institutionOptions' => $institutionOptions,
            'isSuperAdmin' => $isSuperAdmin,
            'canBootstrapAnyLevel' => $canBootstrapAnyLevel,
            'trashedUsers' => $trashedUsers,
        ]);
    }

    public function promote(Request $request, User $target): RedirectResponse
    {
        $actor = $request->user();
        $targetLevel = $actor->role?->promotesToLevel();

        abort_if($targetLevel === null, 403);

        $data = $request->validate(['role' => ['required', 'string']]);

        $newRole = UserRole::tryFrom($data['role']);
        abort_if($newRole === null, 422);

        $data += $request->validate([
            'scope_id' => [$newRole->level() === 'nasional' ? 'nullable' : 'required', 'integer'],
        ]);

        Gate::authorize('promote', [$target, $newRole, $data['scope_id'] ?? null]);

        $update = ['role' => $newRole, 'union_id' => null, 'conference_id' => null, 'church_id' => null, 'institution_id' => null];

        // Keyed by the role being assigned, not the actor's own targetLevel — those only
        // diverge for superadmin's bootstrap capability and for institusi (see class
        // docblock), where a single actor may assign into any of several levels.
        $scopeColumn = match ($newRole->level()) {
            'uni' => 'union_id',
            'daerah' => 'conference_id',
            'gereja' => 'church_id',
            'institusi' => 'institution_id',
            default => null,
        };

        if ($scopeColumn) {
            $update[$scopeColumn] = $data['scope_id'];
        }

        $target->update($update);

        $scopeLabel = match ($scopeColumn) {
            'union_id' => Union::find($data['scope_id'])?->name,
            'conference_id' => Conference::find($data['scope_id'])?->name,
            'church_id' => Church::find($data['scope_id'])?->name,
            'institution_id' => Institution::find($data['scope_id'])?->name,
            default => null,
        };

        AuditLogger::log(
            'user.promoted',
            $target,
            "Menugaskan \"{$target->name}\" sebagai {$newRole->label()}".($scopeLabel ? " ({$scopeLabel})" : '').'.'
        );

        return redirect()->route('admin.users.index', ['tab' => 'unassigned'])
            ->with('status', __('users.assigned', ['name' => $target->name]));
    }

    public function revoke(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('revoke', $target);

        // Captured before the update wipes it — decides which tab to land back on, and
        // labels the audit entry with the role that's being taken away.
        $oldRole = $target->role;
        $wasInstitusi = $oldRole->level() === 'institusi';
        $wasReadOnly = $oldRole->isReadOnly();

        // union_id/conference_id/church_id are deliberately kept: once the role is gone
        // they revert to meaning "this member's home region" (see Lengkapi Profil / the
        // unassigned-list scoping in index() above) rather than "active assignment", so
        // regional admins can still find & re-promote this person later. institution_id
        // has no such dual meaning — institutions aren't part of the region hierarchy — so
        // it's cleared outright.
        $target->update(['role' => null, 'institution_id' => null]);

        AuditLogger::log('user.revoked', $target, "Mencabut peran {$oldRole->label()} dari \"{$target->name}\".");

        $tab = $wasInstitusi ? 'institusi' : ($wasReadOnly ? 'pemimpin' : 'admin');

        return redirect()->route('admin.users.index', ['tab' => $tab])
            ->with('status', __('users.role_revoked', ['name' => $target->name]));
    }

    /**
     * Clears union_id/conference_id/church_id only — role and institution_id are left alone,
     * unlike revoke() above. Lets an admin detach a bogus or unwanted region link (e.g. a
     * church someone typed their own name into via Lengkapi Profil — see
     * CompleteProfileController::findOrCreateChurch()) without also stripping an active
     * Admin/Pimpinan's role. Deliberately allowed on an active role-holder too, per the user's
     * explicit call — this can leave a uni/daerah/gereja-level role without a working scope
     * until reassigned; the confirm dialog (see admin.users.index) warns about exactly that.
     */
    public function releaseRegion(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('releaseRegion', $target);

        $regionLabel = collect([$target->union?->name, $target->conference?->name, $target->church?->name])
            ->filter()
            ->implode(', ');

        $target->update(['union_id' => null, 'conference_id' => null, 'church_id' => null]);

        AuditLogger::log('user.region_released', $target, "Melepas wilayah \"{$regionLabel}\" dari \"{$target->name}\".");

        return $this->redirectToTab($request)->with('status', __('users.region_released', ['name' => $target->name]));
    }

    /**
     * The other half of the "kenapa gereja ini tidak bisa dihapus" loop: Kelola Akun's blocked-
     * delete tooltip (see AccountController::index()) already names the blocking user(s), but
     * hunting them down in Kelola Pengguna's easy-to-miss "Belum Ditugaskan" tab one at a time
     * was still the only way to actually clear them. This does the same union_id/conference_id/
     * church_id release as releaseRegion() above, but for a whole batch of ids submitted
     * straight from that Kelola Akun row — and deliberately restricted to role === null targets
     * only: an active Admin/Pimpinan losing their working scope should stay a deliberate,
     * one-at-a-time decision (see releaseRegion()'s confirm-dialog warning), not something a
     * single bulk click on an unrelated entity's row can do in passing.
     */
    public function releaseRegionBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $targets = User::whereIn('id', $data['user_ids'])->whereNull('role')->get();

        foreach ($targets as $target) {
            Gate::authorize('releaseRegion', $target);
        }

        $names = $targets->pluck('name')->implode(', ');

        User::whereIn('id', $targets->pluck('id'))->update(['union_id' => null, 'conference_id' => null, 'church_id' => null]);

        foreach ($targets as $target) {
            AuditLogger::log('user.region_released', $target, "Melepas wilayah dari \"{$target->name}\" (aksi massal dari Kelola Akun).");
        }

        return back()->with('status', $targets->isEmpty()
            ? __('users.region_released_bulk_none')
            : __('users.region_released_bulk', ['count' => $targets->count(), 'names' => $names]));
    }

    public function destroy(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('delete', $target);

        $target->delete();

        AuditLogger::log('user.deleted', $target, "Menghapus akun \"{$target->name}\".");

        return $this->redirectToTab($request)->with('status', __('users.user_deleted', ['name' => $target->name]));
    }

    public function restore(User $target): RedirectResponse
    {
        $target->restore();

        AuditLogger::log('user.restored', $target, "Memulihkan akun \"{$target->name}\".");

        return redirect()->route('admin.users.index', ['tab' => 'terhapus'])
            ->with('status', __('users.user_restored', ['name' => $target->name]));
    }

    /**
     * Unlike destroy() above (a soft delete), this is permanent — the whole point of this
     * action is clearing a row that's still physically blocking a restrictOnDelete FK
     * elsewhere despite being "deleted" from every normal list.
     */
    public function forceDelete(User $target): RedirectResponse
    {
        $name = $target->name;
        $target->forceDelete();

        AuditLogger::log('user.force_deleted', $target, "Menghapus permanen akun \"{$name}\".");

        return redirect()->route('admin.users.index', ['tab' => 'terhapus'])
            ->with('status', __('users.user_force_deleted', ['name' => $name]));
    }

    /**
     * A minimal edit page for the target's own login/display name (User.name) — distinct
     * from Person.name, which is the separate public-directory profile name edited via
     * people/{person} instead. Reached from Kelola Pengguna's row-actions, so $tab comes
     * through the query string to redirect back to the right tab on save (see redirectToTab()).
     */
    public function edit(Request $request, User $target)
    {
        Gate::authorize('update', $target);

        $tab = in_array($request->query('tab'), ['admin', 'pemimpin', 'institusi', 'terhapus'], true)
            ? $request->query('tab')
            : 'unassigned';

        return view('admin.users.edit', ['target' => $target, 'tab' => $tab]);
    }

    public function update(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('update', $target);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $target->update($data);

        AuditLogger::log('user.updated', $target, "Memperbarui nama akun menjadi \"{$target->name}\".");

        return $this->redirectToTab($request)->with('status', __('users.user_updated', ['name' => $target->name]));
    }

    public function toggleActive(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('toggleActive', $target);

        $target->update(['is_active' => ! $target->is_active]);

        $status = $target->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $target->is_active ? 'user.activated' : 'user.deactivated',
            $target,
            ($target->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." akun \"{$target->name}\"."
        );

        return $this->redirectToTab($request)->with('status', __('users.user_status_changed', ['name' => $target->name, 'status' => $status]));
    }

    public function resendOtp(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('resendOtp', $target);

        abort_if($target->hasVerifiedEmail(), 422);

        $target->sendVerificationOtp();

        return $this->redirectToTab($request)->with('status', __('users.otp_resent_to', ['email' => $target->email]));
    }

    /**
     * destroy()/toggleActive()/resendOtp() are all called from row-actions.blade.php, shared
     * across 4 tabs — tab-switching there is client-side only (see partials/tab-script.blade.php),
     * so the URL never reflects whichever tab is actually visible when the form submits. back()
     * would instead redirect to whatever tab was in the URL at page-load time, landing the admin
     * on the wrong tab. Each form carries its own tab as a hidden field so this can redirect to
     * the tab the row actually lives in, not whatever the URL happened to say.
     */
    private function redirectToTab(Request $request): RedirectResponse
    {
        $tab = in_array($request->input('tab'), ['admin', 'pemimpin', 'institusi', 'terhapus'], true)
            ? $request->input('tab')
            : 'unassigned';

        return redirect()->route('admin.users.index', ['tab' => $tab]);
    }

    private function assignedUsersQuery(User $actor, string $targetLevel, array $roleValues)
    {
        return match ($targetLevel) {
            'nasional' => User::query()->whereIn('role', $roleValues),
            'uni' => User::query()->whereIn('role', $roleValues),
            'daerah' => User::query()->whereIn('role', $roleValues)
                ->whereHas('conference', fn ($q) => $q->where('union_id', $actor->union_id)),
            'gereja' => User::query()->whereIn('role', $roleValues)
                ->whereHas('church', fn ($q) => $q->where('conference_id', $actor->conference_id)),
            default => User::query()->whereRaw('1 = 0'),
        };
    }

    private function scopeOptions(User $actor, string $targetLevel)
    {
        return match ($targetLevel) {
            'uni' => Union::where('is_active', true)->orderBy('name')->get(),
            'daerah' => Conference::where('union_id', $actor->union_id)->where('is_active', true)->orderBy('name')->get(),
            'gereja' => Church::where('conference_id', $actor->conference_id)->where('is_active', true)->orderBy('name')->get(),
            default => collect(),
        };
    }

    private function adminRoleForLevel(string $level): UserRole
    {
        return match ($level) {
            'nasional' => UserRole::AdminNasional,
            'uni' => UserRole::AdminUni,
            'daerah' => UserRole::AdminDaerah,
            'gereja' => UserRole::AdminGereja,
        };
    }

    private function pimpinanRoleForLevel(string $level): UserRole
    {
        return match ($level) {
            'nasional' => UserRole::PimpinanNasional,
            'uni' => UserRole::PimpinanUni,
            'daerah' => UserRole::PimpinanDaerah,
            'gereja' => UserRole::PimpinanGereja,
        };
    }

    private function rolesForLevel(string $level): array
    {
        return match ($level) {
            'nasional' => [UserRole::AdminNasional, UserRole::PimpinanNasional],
            'uni' => [UserRole::AdminUni, UserRole::PimpinanUni],
            'daerah' => [UserRole::AdminDaerah, UserRole::PimpinanDaerah],
            'gereja' => [UserRole::AdminGereja, UserRole::PimpinanGereja],
            default => [],
        };
    }
}
