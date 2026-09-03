<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'auto_fetch_enabled', 'auto_fetch_day', 'auto_fetch_time',
        'cs_whatsapp_number',
        'apify_fallback_to_manual', 'apify_token', 'youtube_api_key',
        'youtube_enabled', 'instagram_enabled', 'tiktok_enabled', 'facebook_enabled', 'x_enabled', 'threads_enabled',
    ];

    protected $casts = [
        'auto_fetch_enabled' => 'boolean',
        'auto_fetch_day' => 'integer',
        'apify_fallback_to_manual' => 'boolean',
        'youtube_enabled' => 'boolean',
        'instagram_enabled' => 'boolean',
        'tiktok_enabled' => 'boolean',
        'facebook_enabled' => 'boolean',
        'x_enabled' => 'boolean',
        'threads_enabled' => 'boolean',
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
