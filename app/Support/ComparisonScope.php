<?php

namespace App\Support;

/**
 * Distinguishes the church/personal/institution-scoped variants of the comparison/leaderboard/
 * presentation pages, which are otherwise structurally identical. Passed into the shared
 * Blade views so they can resolve the right route names, labels, and row keys without
 * duplicating markup per scope. Institution has no public presentation page (see
 * presentationUrl()/presentationGrowthUrl() below) and no other()-counterpart — those two
 * remain a church<->personal pair only, per the user's explicit call.
 */
class ComparisonScope
{
    public function __construct(public readonly string $type)
    {
    }

    public static function church(): self
    {
        return new self('gereja');
    }

    public static function personal(): self
    {
        return new self('personal');
    }

    public static function institution(): self
    {
        return new self('institusi');
    }

    /** Union + Conference-owned accounts, shown together as one "Organisasi" scope — see BuildsLeaderboards::analyticsOrganizationScope(). */
    public static function organization(): self
    {
        return new self('organisasi');
    }

    public function isChurch(): bool
    {
        return $this->type === 'gereja';
    }

    public function isPersonal(): bool
    {
        return $this->type === 'personal';
    }

    public function isInstitution(): bool
    {
        return $this->type === 'institusi';
    }

    public function isOrganization(): bool
    {
        return $this->type === 'organisasi';
    }

    /** The key holding the entity model on a comparison row: $row['church']/$row['person']/$row['institution']/$row['organization']. */
    public function rowKey(): string
    {
        return match ($this->type) {
            'gereja' => 'church',
            'personal' => 'person',
            'institusi' => 'institution',
            'organisasi' => 'organization',
        };
    }

    /** Table column header for the entity name. */
    public function nameLabel(): string
    {
        return match ($this->type) {
            'gereja' => __('common.church'),
            'personal' => __('common.name'),
            'institusi' => __('common.institution'),
            'organisasi' => __('comparison.organization_name_label'),
        };
    }

    /** Noun used in sentences like "Peringkat X {noun}". */
    public function noun(): string
    {
        return match ($this->type) {
            'gereja' => mb_strtolower(__('common.church')),
            'personal' => mb_strtolower(__('common.personal')),
            'institusi' => mb_strtolower(__('common.institution')),
            'organisasi' => mb_strtolower(__('comparison.organization_noun')),
        };
    }

    /** " Personal"/" Institusi"/" Uni/Daerah" suffix appended to church-side titles, or '' for the church scope itself. */
    public function titleSuffix(): string
    {
        return match ($this->type) {
            'gereja' => '',
            'personal' => __('comparison.title_suffix_personal'),
            'institusi' => __('comparison.title_suffix_institution'),
            'organisasi' => __('comparison.title_suffix_organization'),
        };
    }

    /** Capitalized label: "Gereja", "Personal", "Institusi", or "Uni/Daerah". */
    public function labelCap(): string
    {
        return match ($this->type) {
            'gereja' => __('common.church'),
            'personal' => __('common.personal'),
            'institusi' => __('common.institution'),
            'organisasi' => __('comparison.organization_label'),
        };
    }

    /** Icon name representing this scope, for nav links that switch to it. */
    public function icon(): string
    {
        return match ($this->type) {
            'gereja' => 'building-office',
            'personal' => 'user',
            'institusi' => 'building-office',
            'organisasi' => 'flag',
        };
    }

    /** "for all X" phrase, used on the metric-comparison score subtitle. */
    public function forAllLabel(): string
    {
        return match ($this->type) {
            'gereja' => __('comparison.for_all_churches'),
            'personal' => __('comparison.for_all_personal'),
            'institusi' => __('comparison.for_all_institutions'),
            'organisasi' => __('comparison.for_all_organizations'),
        };
    }

    /** Plural noun used in section subtitles like ":count :noun, sorted by :basis". */
    public function scopeNoun(): string
    {
        return match ($this->type) {
            'gereja' => __('comparison.scope_church'),
            'personal' => __('comparison.scope_personal'),
            'institusi' => __('comparison.scope_institution'),
            'organisasi' => __('comparison.scope_organization'),
        };
    }

    /**
     * The opposite scope — church <-> personal only. Never called on an institution/organisasi
     * scope; neither has a public presentation page to switch to/from (see presentationUrl()
     * below), so each maps to itself as a safe fallback rather than throwing.
     */
    public function other(): self
    {
        return match ($this->type) {
            'gereja' => self::personal(),
            'personal' => self::church(),
            'institusi' => self::institution(),
            'organisasi' => self::organization(),
        };
    }

    public function metricComparisonUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'churches.metric-comparison',
            'personal' => 'people.metric-comparison',
            'institusi' => 'institutions.metric-comparison',
            'organisasi' => 'organizations.metric-comparison',
        }, $params);
    }

    public function leaderboardUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'churches.leaderboard',
            'personal' => 'people.leaderboard',
            'institusi' => 'institutions.leaderboard',
            'organisasi' => 'organizations.leaderboard',
        }, $params);
    }

    public function platformComparisonUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'churches.platform-comparison',
            'personal' => 'people.platform-comparison',
            'institusi' => 'institutions.platform-comparison',
            'organisasi' => 'organizations.platform-comparison',
        }, $params);
    }

    public function analyticsUrl(): string
    {
        return match ($this->type) {
            'gereja' => route('churches.analytics'),
            'personal' => route('churches.analytics', ['tab' => 'personal']),
            'institusi' => route('churches.analytics', ['tab' => 'institusi']),
            'organisasi' => route('churches.analytics', ['tab' => 'organisasi']),
        };
    }

    /**
     * Union/Conference have no public read-only "show" page of their own (unlike Church/Person/
     * Institution) — only the admin-gated socials-management page, which not every viewer who
     * can see a leaderboard/comparison row is guaranteed to have access to. Callers on the
     * organisasi scope should check can('update', $entity) before linking anywhere with this,
     * or just not link the name at all (see partials/leaderboard-row.blade.php /
     * growth-score-row.blade.php, which do exactly that).
     */
    public function showUrl($entity): ?string
    {
        return match ($this->type) {
            'gereja' => route('churches.show', $entity),
            'personal' => route('people.show', $entity),
            'institusi' => route('institutions.show', $entity),
            'organisasi' => null,
        };
    }

    /** Where the leaderboard page's back-link should point — same as every other comparison page: straight back to Analitik & Grafik. */
    public function leaderboardBackUrl(): string
    {
        return $this->analyticsUrl();
    }

    /** Label for the leaderboard page's back-link. */
    public function leaderboardBackLabel(): string
    {
        return __('comparison.leaderboard_back_analytics');
    }

    /** Institution/organisasi have no public presentation board — never call this on those scopes. */
    public function presentationUrl(): string
    {
        return match ($this->type) {
            'gereja' => route('churches.presentation'),
            'personal' => route('people.presentation'),
            'institusi' => throw new \LogicException('Institution has no public presentation page.'),
            'organisasi' => throw new \LogicException('Organisasi has no public presentation page.'),
        };
    }

    /** Institution/organisasi have no public presentation board — never call this on those scopes. */
    public function presentationGrowthUrl(): string
    {
        return match ($this->type) {
            'gereja' => route('churches.presentation-growth'),
            'personal' => route('people.presentation-growth'),
            'institusi' => throw new \LogicException('Institution has no public presentation page.'),
            'organisasi' => throw new \LogicException('Organisasi has no public presentation page.'),
        };
    }

    /**
     * Organisasi has no export support yet (no ExportController methods exist for it) — every
     * export* method below returns null for it instead of routing anywhere, and every
     * <x-export-button> call site on an organisasi-scoped view is wrapped in an isOrganization()
     * check that skips rendering the button at all, so this is never actually dereferenced.
     */
    public function exportMetricComparisonUrl(array $params = []): ?string
    {
        return match ($this->type) {
            'gereja' => route('export.metric-comparison.preview', $params),
            'personal' => route('export.personal-metric-comparison.preview', $params),
            'institusi' => route('export.institution-metric-comparison.preview', $params),
            'organisasi' => null,
        };
    }

    public function exportLeaderboardUrl(array $params = []): ?string
    {
        return match ($this->type) {
            'gereja' => route('export.leaderboard.preview', $params),
            'personal' => route('export.personal-leaderboard.preview', $params),
            'institusi' => route('export.institution-leaderboard.preview', $params),
            'organisasi' => null,
        };
    }

    public function exportPlatformComparisonUrl(array $params = []): ?string
    {
        return match ($this->type) {
            'gereja' => route('export.platform.preview', $params),
            'personal' => route('export.personal-platform.preview', $params),
            'institusi' => route('export.institution-platform.preview', $params),
            'organisasi' => null,
        };
    }

    public function exportPlatformOverviewUrl(array $params = []): ?string
    {
        return match ($this->type) {
            'gereja' => route('export.platform-overview.preview', $params),
            'personal' => route('export.personal-platform-overview.preview', $params),
            'institusi' => route('export.institution-platform-overview.preview', $params),
            'organisasi' => null,
        };
    }
}
