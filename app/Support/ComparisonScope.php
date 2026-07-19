<?php

namespace App\Support;

/**
 * Distinguishes the church-scoped vs personal-scoped variant of the comparison/leaderboard/
 * presentation pages, which are otherwise structurally identical. Passed into the shared
 * Blade views so they can resolve the right route names, labels, and row keys without
 * duplicating markup per scope.
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

    public function isChurch(): bool
    {
        return $this->type === 'gereja';
    }

    /** The key holding the entity model on a comparison row: $row['church'] or $row['person']. */
    public function rowKey(): string
    {
        return $this->isChurch() ? 'church' : 'person';
    }

    /** Table column header for the entity name. */
    public function nameLabel(): string
    {
        return $this->isChurch() ? __('common.church') : __('common.name');
    }

    /** Noun used in sentences like "Peringkat X {noun}". */
    public function noun(): string
    {
        return $this->isChurch() ? mb_strtolower(__('common.church')) : mb_strtolower(__('common.personal'));
    }

    /** " Personal" suffix appended to church-side titles, or '' for the church scope itself. */
    public function titleSuffix(): string
    {
        return $this->isChurch() ? '' : __('comparison.title_suffix_personal');
    }

    /** Capitalized label: "Gereja" or "Personal". */
    public function labelCap(): string
    {
        return $this->isChurch() ? __('common.church') : __('common.personal');
    }

    /** Icon name representing this scope, for nav links that switch to it. */
    public function icon(): string
    {
        return $this->isChurch() ? 'building-office' : 'user';
    }

    /** The opposite scope — church <-> personal. */
    public function other(): self
    {
        return $this->isChurch() ? self::personal() : self::church();
    }

    public function metricComparisonUrl(array $params = []): string
    {
        return route($this->isChurch() ? 'churches.metric-comparison' : 'people.metric-comparison', $params);
    }

    public function leaderboardUrl(array $params = []): string
    {
        return route($this->isChurch() ? 'churches.leaderboard' : 'people.leaderboard', $params);
    }

    public function platformComparisonUrl(array $params = []): string
    {
        return route($this->isChurch() ? 'churches.platform-comparison' : 'people.platform-comparison', $params);
    }

    public function analyticsUrl(): string
    {
        return $this->isChurch() ? route('churches.analytics') : route('churches.analytics', ['tab' => 'personal']);
    }

    public function showUrl($entity): string
    {
        return route($this->isChurch() ? 'churches.show' : 'people.show', $entity);
    }

    /** Where the leaderboard page's back-link should point. */
    public function leaderboardBackUrl(): string
    {
        return $this->isChurch() ? $this->analyticsUrl() : $this->metricComparisonUrl();
    }

    /** Label for the leaderboard page's back-link. */
    public function leaderboardBackLabel(): string
    {
        return $this->isChurch() ? __('comparison.leaderboard_back_analytics') : __('comparison.leaderboard_back_metric');
    }

    public function presentationUrl(): string
    {
        return route($this->isChurch() ? 'churches.presentation' : 'people.presentation');
    }

    public function presentationGrowthUrl(): string
    {
        return route($this->isChurch() ? 'churches.presentation-growth' : 'people.presentation-growth');
    }

    public function exportMetricComparisonUrl(array $params = []): string
    {
        return route($this->isChurch() ? 'export.metric-comparison.preview' : 'export.personal-metric-comparison.preview', $params);
    }

    public function exportLeaderboardUrl(array $params = []): string
    {
        return route($this->isChurch() ? 'export.leaderboard.preview' : 'export.personal-leaderboard.preview', $params);
    }

    public function exportPlatformComparisonUrl(array $params = []): string
    {
        return route($this->isChurch() ? 'export.platform.preview' : 'export.personal-platform.preview', $params);
    }

    public function exportPlatformOverviewUrl(array $params = []): string
    {
        return route($this->isChurch() ? 'export.platform-overview.preview' : 'export.personal-platform-overview.preview', $params);
    }
}
