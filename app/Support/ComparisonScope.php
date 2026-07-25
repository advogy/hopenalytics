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

    /** The key holding the entity model on a comparison row: $row['church']/$row['person']/$row['institution']. */
    public function rowKey(): string
    {
        return match ($this->type) {
            'gereja' => 'church',
            'personal' => 'person',
            'institusi' => 'institution',
        };
    }

    /** Table column header for the entity name. */
    public function nameLabel(): string
    {
        return match ($this->type) {
            'gereja' => __('common.church'),
            'personal' => __('common.name'),
            'institusi' => __('common.institution'),
        };
    }

    /** Noun used in sentences like "Peringkat X {noun}". */
    public function noun(): string
    {
        return match ($this->type) {
            'gereja' => mb_strtolower(__('common.church')),
            'personal' => mb_strtolower(__('common.personal')),
            'institusi' => mb_strtolower(__('common.institution')),
        };
    }

    /** " Personal"/" Institusi" suffix appended to church-side titles, or '' for the church scope itself. */
    public function titleSuffix(): string
    {
        return match ($this->type) {
            'gereja' => '',
            'personal' => __('comparison.title_suffix_personal'),
            'institusi' => __('comparison.title_suffix_institution'),
        };
    }

    /** Capitalized label: "Gereja", "Personal", or "Institusi". */
    public function labelCap(): string
    {
        return match ($this->type) {
            'gereja' => __('common.church'),
            'personal' => __('common.personal'),
            'institusi' => __('common.institution'),
        };
    }

    /** Icon name representing this scope, for nav links that switch to it. */
    public function icon(): string
    {
        return match ($this->type) {
            'gereja' => 'building-office',
            'personal' => 'user',
            'institusi' => 'building-office',
        };
    }

    /** "for all X" phrase, used on the metric-comparison score subtitle. */
    public function forAllLabel(): string
    {
        return match ($this->type) {
            'gereja' => __('comparison.for_all_churches'),
            'personal' => __('comparison.for_all_personal'),
            'institusi' => __('comparison.for_all_institutions'),
        };
    }

    /** Plural noun used in section subtitles like ":count :noun, sorted by :basis". */
    public function scopeNoun(): string
    {
        return match ($this->type) {
            'gereja' => __('comparison.scope_church'),
            'personal' => __('comparison.scope_personal'),
            'institusi' => __('comparison.scope_institution'),
        };
    }

    /**
     * The opposite scope — church <-> personal only. Never called on an institution scope;
     * institution has no public presentation page to switch to/from (see presentationUrl()
     * below), so it maps to itself as a safe fallback rather than throwing.
     */
    public function other(): self
    {
        return match ($this->type) {
            'gereja' => self::personal(),
            'personal' => self::church(),
            'institusi' => self::institution(),
        };
    }

    public function metricComparisonUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'churches.metric-comparison',
            'personal' => 'people.metric-comparison',
            'institusi' => 'institutions.metric-comparison',
        }, $params);
    }

    public function leaderboardUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'churches.leaderboard',
            'personal' => 'people.leaderboard',
            'institusi' => 'institutions.leaderboard',
        }, $params);
    }

    public function platformComparisonUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'churches.platform-comparison',
            'personal' => 'people.platform-comparison',
            'institusi' => 'institutions.platform-comparison',
        }, $params);
    }

    public function analyticsUrl(): string
    {
        return match ($this->type) {
            'gereja' => route('churches.analytics'),
            'personal' => route('churches.analytics', ['tab' => 'personal']),
            'institusi' => route('churches.analytics', ['tab' => 'institusi']),
        };
    }

    public function showUrl($entity): string
    {
        return route(match ($this->type) {
            'gereja' => 'churches.show',
            'personal' => 'people.show',
            'institusi' => 'institutions.show',
        }, $entity);
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

    /** Institution has no public presentation board — never call this on an institution scope. */
    public function presentationUrl(): string
    {
        return match ($this->type) {
            'gereja' => route('churches.presentation'),
            'personal' => route('people.presentation'),
            'institusi' => throw new \LogicException('Institution has no public presentation page.'),
        };
    }

    /** Institution has no public presentation board — never call this on an institution scope. */
    public function presentationGrowthUrl(): string
    {
        return match ($this->type) {
            'gereja' => route('churches.presentation-growth'),
            'personal' => route('people.presentation-growth'),
            'institusi' => throw new \LogicException('Institution has no public presentation page.'),
        };
    }

    public function exportMetricComparisonUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'export.metric-comparison.preview',
            'personal' => 'export.personal-metric-comparison.preview',
            'institusi' => 'export.institution-metric-comparison.preview',
        }, $params);
    }

    public function exportLeaderboardUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'export.leaderboard.preview',
            'personal' => 'export.personal-leaderboard.preview',
            'institusi' => 'export.institution-leaderboard.preview',
        }, $params);
    }

    public function exportPlatformComparisonUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'export.platform.preview',
            'personal' => 'export.personal-platform.preview',
            'institusi' => 'export.institution-platform.preview',
        }, $params);
    }

    public function exportPlatformOverviewUrl(array $params = []): string
    {
        return route(match ($this->type) {
            'gereja' => 'export.platform-overview.preview',
            'personal' => 'export.personal-platform-overview.preview',
            'institusi' => 'export.institution-platform-overview.preview',
        }, $params);
    }
}
