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
            ->with('status', "\"{$target->name}\" berhasil ditugaskan.");
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
            ->with('status', "Peran \"{$target->name}\" telah dicabut.");
    }

    public function destroy(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('delete', $target);

        $target->delete();

        AuditLogger::log('user.deleted', $target, "Menghapus akun \"{$target->name}\".");

        return back()->with('status', "\"{$target->name}\" telah dihapus.");
    }

    public function restore(User $target): RedirectResponse
    {
        $target->restore();

        AuditLogger::log('user.restored', $target, "Memulihkan akun \"{$target->name}\".");

        return redirect()->route('admin.users.index', ['tab' => 'terhapus'])
            ->with('status', "\"{$target->name}\" berhasil dipulihkan.");
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
            ->with('status', "\"{$name}\" berhasil dihapus permanen.");
    }

    public function toggleActive(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('toggleActive', $target);

        $target->update(['is_active' => ! $target->is_active]);

        $status = $target->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        AuditLogger::log(
            $target->is_active ? 'user.activated' : 'user.deactivated',
            $target,
            ($target->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." akun \"{$target->name}\"."
        );

        return back()->with('status', "\"{$target->name}\" telah {$status}.");
    }

    public function resendOtp(Request $request, User $target): RedirectResponse
    {
        Gate::authorize('resendOtp', $target);

        abort_if($target->hasVerifiedEmail(), 422);

        $target->sendVerificationOtp();

        return back()->with('status', "Kode OTP baru telah dikirim ke {$target->email}.");
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
