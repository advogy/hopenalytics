<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'auto_fetch_enabled', 'auto_fetch_day', 'auto_fetch_time',
        'cs_whatsapp_number',
        'bulk_email_delay_seconds',
        'apify_fallback_to_manual', 'apify_token', 'youtube_api_key',
        'youtube_enabled', 'instagram_enabled', 'tiktok_enabled', 'facebook_enabled', 'x_enabled', 'threads_enabled',
        'metric_posts_enabled', 'metric_views_enabled', 'metric_likes_enabled',
        'metric_comments_enabled', 'metric_shares_enabled', 'metric_reach_enabled',
    ];

    protected $casts = [
        'auto_fetch_enabled' => 'boolean',
        'auto_fetch_day' => 'integer',
        'bulk_email_delay_seconds' => 'integer',
        'apify_fallback_to_manual' => 'boolean',
        'youtube_enabled' => 'boolean',
        'instagram_enabled' => 'boolean',
        'tiktok_enabled' => 'boolean',
        'facebook_enabled' => 'boolean',
        'x_enabled' => 'boolean',
        'threads_enabled' => 'boolean',
        'metric_posts_enabled' => 'boolean',
        'metric_views_enabled' => 'boolean',
        'metric_likes_enabled' => 'boolean',
        'metric_comments_enabled' => 'boolean',
        'metric_shares_enabled' => 'boolean',
        'metric_reach_enabled' => 'boolean',
    ];

    // Single source of truth for "which platforms this app tracks and their display
    // labels" — was previously duplicated as a literal ~10 times across controllers and
    // Blade views; now also doubles as the platform-visibility toggle's backing data
    // (see ChurchSocial's global scope, which reads enabledPlatformValues()).
    private const PLATFORM_LABELS = [
        'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'x' => 'X', 'threads' => 'Threads',
    ];

    private const PLATFORM_COLUMNS = [
        'youtube' => 'youtube_enabled', 'instagram' => 'instagram_enabled', 'tiktok' => 'tiktok_enabled',
        'facebook' => 'facebook_enabled', 'x' => 'x_enabled', 'threads' => 'threads_enabled',
    ];

    // Same "single source of truth" role as PLATFORM_LABELS above, but for which metric
    // *components* (not platforms) show up across Perbandingan Metrik, Perbandingan Platform,
    // an entity's own growth-score detail, and the dashboard's ranked score cards — per the
    // user's explicit call for one superadmin-only toggle that governs all of them at once.
    // Canonical order matches the reordered Perbandingan Metrik tabs (Post/View/Like/Comment/
    // Share/Followers-Subscribers).
    private const METRIC_LABELS = [
        'posts' => 'Post / Video', 'views' => 'Views', 'likes' => 'Likes',
        'comments' => 'Comments', 'shares' => 'Shares', 'reach' => 'Followers/Subscribers',
    ];

    private const METRIC_COLUMNS = [
        'posts' => 'metric_posts_enabled', 'views' => 'metric_views_enabled', 'likes' => 'metric_likes_enabled',
        'comments' => 'metric_comments_enabled', 'shares' => 'metric_shares_enabled', 'reach' => 'metric_reach_enabled',
    ];

    /**
     * The single settings row, created with defaults on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /** Platform values (e.g. 'youtube') currently enabled, in canonical display order. */
    public function enabledPlatformValues(): array
    {
        return array_keys(array_filter(self::PLATFORM_COLUMNS, fn ($column) => (bool) $this->{$column}));
    }

    /** Same as enabledPlatformValues(), but value => label, for driving <select>/legend/label lists. */
    public function enabledPlatformLabels(): array
    {
        return array_intersect_key(self::PLATFORM_LABELS, array_flip($this->enabledPlatformValues()));
    }

    /**
     * Every tracked platform's value => label, regardless of enabled state, plus its
     * `{platform}_enabled` column name — for Settings' platform-visibility card, which
     * needs to render a checkbox for all 5 (some checked, some not), not just the
     * currently-enabled subset enabledPlatformLabels() returns.
     */
    public static function allPlatforms(): array
    {
        return collect(self::PLATFORM_LABELS)->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
            'column' => self::PLATFORM_COLUMNS[$value],
        ])->values()->all();
    }

    /** Metric values (e.g. 'posts') currently enabled, in canonical display order. */
    public function enabledMetricValues(): array
    {
        return array_keys(array_filter(self::METRIC_COLUMNS, fn ($column) => (bool) $this->{$column}));
    }

    /** Same as enabledMetricValues(), but value => label, for driving pill/tab/card lists. */
    public function enabledMetricLabels(): array
    {
        return array_intersect_key(self::METRIC_LABELS, array_flip($this->enabledMetricValues()));
    }

    /**
     * Every tracked metric's value => label, regardless of enabled state, plus its
     * `metric_{x}_enabled` column name — for Settings' metric-visibility card, which needs a
     * checkbox for all 6, not just the currently-enabled subset enabledMetricLabels() returns.
     */
    public static function allMetrics(): array
    {
        return collect(self::METRIC_LABELS)->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
            'column' => self::METRIC_COLUMNS[$value],
        ])->values()->all();
    }

    /**
     * Filters any metric label array ($value => $label, e.g. one of the many hardcoded
     * literals across ChurchDashboardController/ExportController/the growth-score-*
     * components) down to just the currently-enabled ones, preserving canonical order — the
     * one place every one of those call sites funnels through, so a single settings toggle
     * governs all of them without each needing its own AppSetting::current() call.
     */
    public static function filterEnabledMetrics(array $labels): array
    {
        return array_intersect_key($labels, array_flip(static::current()->enabledMetricValues()));
    }

    /** Same as filterEnabledMetrics(), for a plain list of metric keys (no labels) — e.g. growthScoreRows()'s own $metrics = ['reach', 'views', ...]. */
    public static function filterEnabledMetricKeys(array $keys): array
    {
        return array_values(array_intersect($keys, static::current()->enabledMetricValues()));
    }

    /**
     * A wa.me link built from cs_whatsapp_number, or null if it's unset — strips everything but
     * digits so however the superadmin formats it (spaces, dashes, a leading +) still works,
     * as long as the digits themselves are in international format (country code, no leading 0).
     */
    public function csWhatsappUrl(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->cs_whatsapp_number);

        return $digits !== '' ? "https://wa.me/{$digits}" : null;
    }
}
