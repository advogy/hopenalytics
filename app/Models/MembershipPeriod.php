<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A stretch of time the member engagement target applies to. Global admins create these and
 * set each one's target share (10% by default) — see MembershipPeriodController.
 */
class MembershipPeriod extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'target_percent', 'updated_by'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'target_percent' => 'float',
    ];

    /** The period covering today, or else the one that started most recently. */
    public static function current(): ?self
    {
        $today = now()->toDateString();

        return static::query()
            ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)
            ->orderByDesc('starts_on')->first()
            ?? static::query()->orderByDesc('starts_on')->first();
    }

    /** How many people make up the target for a given membership, rounded up. */
    public function targetFor(?int $members): ?int
    {
        return $members === null ? null : (int) ceil($members * $this->target_percent / 100);
    }
}
