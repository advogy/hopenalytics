<?php

namespace App\Support;

use App\Http\Controllers\Admin\AccountController;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;

/**
 * The admin pages grouped into four tabbed "hubs", each one entry in the account menu:
 *  - accounts:   Kelola Akun's own tabs + Impor Massal
 *  - goals:      Target Metrik, Periode, Unggah Data Anggota, Konfirmasi Data Anggota
 *  - settings:   Pengaturan's own tabs + Brand + Hashtag
 *  - monitoring: Antrean + Log Audit
 *
 * The pages stay separate routes; each shows its hub's tab bar (components/hub-header), so
 * old links keep working. Every tab carries its own permission, so a viewer only sees the
 * tabs they may open and the menu entry links to the first of them.
 */
class AdminHubs
{
    /** @return list<array{key:string, label:string, url:string, active:bool, badge:int}> */
    public static function tabs(string $hub, User $user): array
    {
        // Kept for the rest of the request: the account menu is rendered twice per page
        // (desktop dropdown and mobile menu), and each hub's tabs cost permission checks and
        // the pending-membership count.
        $key = "admin_hubs.{$hub}.{$user->id}";
        $attributes = request()->attributes;

        if (! $attributes->has($key)) {
            $attributes->set($key, self::build($hub, $user));
        }

        return $attributes->get($key);
    }

    private static function build(string $hub, User $user): array
    {
        return collect(self::definitions($hub, $user))
            ->filter(fn ($tab) => $tab['can'])
            ->map(fn ($tab) => [
                'key' => $tab['key'],
                'label' => $tab['label'],
                'url' => $tab['url'],
                'active' => $tab['routes'] !== [] && request()->routeIs(...$tab['routes']),
                'badge' => $tab['badge'] ?? 0,
                'note' => __("tabs.{$hub}_{$tab['key']}"),
            ])
            ->values()->all();
    }

    /** The account-menu entry for a hub, or null when the viewer can't open any of its tabs. */
    public static function entry(string $hub, User $user): ?array
    {
        $tabs = self::tabs($hub, $user);

        if ($tabs === []) {
            return null;
        }

        return [
            'label' => __('nav.hub_'.$hub),
            'url' => $tabs[0]['url'],
            'badge' => array_sum(array_column($tabs, 'badge')),
        ];
    }

    private static function definitions(string $hub, User $user): array
    {
        return match ($hub) {
            'accounts' => [
                ...collect(AccountController::visibleTabsFor($user))->filter()->keys()->map(fn ($tab) => [
                    'key' => $tab,
                    'label' => __('common.'.['divisi' => 'division', 'uni' => 'union', 'daerah' => 'conference', 'gereja' => 'church', 'institusi' => 'institution', 'personal' => 'personal'][$tab]),
                    'url' => route('admin.accounts.index', ['tab' => $tab]),
                    'can' => $user->can('manage-people'),
                    'routes' => [], // in-page tabs on Kelola Akun itself
                ])->all(),
                ['key' => 'impor', 'label' => __('nav.bulk_import'), 'url' => route('admin.bulk-import.index'), 'can' => $user->can('manage-hierarchy'), 'routes' => ['admin.bulk-import.*']],
            ],
            'goals' => [
                ['key' => 'target', 'label' => __('nav.goal_targets'), 'url' => route('goals.edit'), 'can' => $user->can('manage-goals'), 'routes' => ['goals.edit']],
                ['key' => 'periode', 'label' => __('nav.engagement_periods'), 'url' => route('engagement.periods.index'), 'can' => $user->can('manage-goals'), 'routes' => ['engagement.periods.*']],
                ['key' => 'unggah', 'label' => __('nav.membership_upload'), 'url' => route('engagement.import.index'), 'can' => $user->can('upload-membership'), 'routes' => ['engagement.import.*']],
                [
                    'key' => 'konfirmasi', 'label' => __('nav.membership_confirm'), 'url' => route('engagement.confirm.index'),
                    'can' => $can = $user->can('confirm-membership'), 'routes' => ['engagement.confirm.*'],
                    'badge' => $can ? self::pendingMembershipCount($user) : 0,
                ],
            ],
            'settings' => [
                ['key' => 'general', 'label' => __('settings.tab_general'), 'url' => route('settings.edit', ['tab' => 'general']), 'can' => $user->can('manage-settings'), 'routes' => []],
                ['key' => 'platform', 'label' => __('settings.tab_platform'), 'url' => route('settings.edit', ['tab' => 'platform']), 'can' => $user->can('manage-settings') && $user->can('manage-platform-visibility'), 'routes' => []],
                ['key' => 'metric', 'label' => __('settings.tab_metric'), 'url' => route('settings.edit', ['tab' => 'metric']), 'can' => $user->can('manage-settings') && $user->can('manage-platform-visibility'), 'routes' => []],
                ['key' => 'coordinator', 'label' => __('settings.tab_coordinator'), 'url' => route('settings.edit', ['tab' => 'coordinator']), 'can' => $user->can('manage-settings'), 'routes' => []],
                ['key' => 'brand', 'label' => __('brands.title'), 'url' => route('admin.brands.index'), 'can' => $user->can('manage-settings'), 'routes' => ['admin.brands.*']],
                ['key' => 'hashtag', 'label' => __('nav.hashtags'), 'url' => route('admin.hashtags.index'), 'can' => $user->can('manage-settings'), 'routes' => ['admin.hashtags.*']],
            ],
            'monitoring' => [
                ['key' => 'antrean', 'label' => __('nav.queue_short'), 'url' => route('queue.index'), 'can' => $user->can('manage-queue'), 'routes' => ['queue.*']],
                ['key' => 'audit', 'label' => __('audit.title'), 'url' => route('admin.audit-log.index'), 'can' => $user->can('view-audit-log'), 'routes' => ['admin.audit-log.*']],
            ],
            default => throw new \InvalidArgumentException("Unknown admin hub [{$hub}]."),
        };
    }

    private static function pendingMembershipCount(User $user): int
    {
        return ChurchMembership::query()
            ->where('status', ChurchMembership::PENDING)
            ->whereIn('church_id', Church::query()->visibleTo($user)->select('id'))
            ->count();
    }
}
