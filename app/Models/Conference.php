<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conference extends Model
{
    use HasFactory;

    protected $fillable = ['union_id', 'name', 'slug', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
