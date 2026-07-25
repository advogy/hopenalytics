<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = ['metric', 'target_year', 'target_value', 'updated_by'];

    protected $casts = [
        'target_year' => 'integer',
        'target_value' => 'integer',
    ];

    /** Every metric a national target can be set for — same set as the leaderboard/comparison pages. */
    public const METRICS = ['reach', 'views', 'likes', 'posts'];

    /** The single goal row for a metric, created with today's year and a zero target on first access. */
    public static function forMetric(string $metric): self
    {
        return static::query()->firstOrCreate(
            ['metric' => $metric],
            ['target_year' => now()->year, 'target_value' => 0],
        );
    }
}
