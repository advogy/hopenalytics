<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Union extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active', 'coordinator_whatsapp_number', 'whatsapp_group_link'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function conferences(): HasMany
    {
        return $this->hasMany(Conference::class);
    }

    /**
     * Same scoping shape as Church::scopeVisibleTo() — a daerah-level admin can still *see*
     * their own parent Uni (for context in Kelola Akun), even though UnionPolicy::update()
     * additionally excludes daerah level, since editing a level above your own isn't "managing
     * your own region".
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query;
        }

        if ($user->role === null) {
            return $query->whereRaw('1 = 0');
        }

        return match (true) {
            $user->role->hasNasionalAccess() || $user->role->level() === 'nasional' => $query,
            $user->role->level() === 'uni' => $query->where('id', $user->union_id),
            $user->role->level() === 'daerah' => $query->whereHas(
                'conferences', fn (Builder $q) => $q->where('id', $user->conference_id)
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function socials(): HasMany
    {
        return $this->hasMany(ChurchSocial::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * A wa.me link built from this Union's own coordinator_whatsapp_number, or null if it's
     * unset — same digit-stripping as AppSetting::csWhatsappUrl(), so the floating Customer
     * Service widget can fall back to the national coordinator when a Union hasn't set its own.
     */
    public function coordinatorWhatsappUrl(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->coordinator_whatsapp_number);

        return $digits !== '' ? "https://wa.me/{$digits}" : null;
    }
}
