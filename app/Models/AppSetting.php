<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['auto_fetch_enabled', 'auto_fetch_day', 'auto_fetch_time'];

    protected $casts = [
        'auto_fetch_enabled' => 'boolean',
        'auto_fetch_day' => 'integer',
    ];

    /**
     * The single settings row, created with defaults on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
