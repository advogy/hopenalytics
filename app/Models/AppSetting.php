<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'auto_fetch_enabled', 'auto_fetch_day', 'auto_fetch_time',
        'cs_whatsapp_number', 'cs_whatsapp_group_link',
        'apify_fallback_to_manual', 'apify_token', 'youtube_api_key',
    ];

    protected $casts = [
        'auto_fetch_enabled' => 'boolean',
        'auto_fetch_day' => 'integer',
        'apify_fallback_to_manual' => 'boolean',
    ];

    /**
     * The single settings row, created with defaults on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
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
