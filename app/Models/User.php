<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\OtpVerificationMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'division_id',
        'union_id',
        'conference_id',
        'church_id',
        'institution_id',
        'locale',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function person(): HasOne
    {
        return $this->hasOne(Person::class);
    }

    /**
     * Which Unions a scoped Admin/Pimpinan Nasional is assigned to (see UserRole::level()'s
     * doc comment for why this is a set rather than a single union_id column like
     * uni/daerah/gereja/institusi levels use). Meaningless for every other role.
     */
    public function assignedUnions(): BelongsToMany
    {
        return $this->belongsToMany(Union::class, 'admin_nasional_unions');
    }

    /** Plain id array — what every scopeVisibleTo()/scopeManageableBy() whereIn() below needs. */
    public function assignedUnionIds(): array
    {
        return $this->assignedUnions()->pluck('unions.id')->all();
    }

    /**
     * Generates a fresh 6-digit code and emails it — shared by registration verification
     * and admin-triggered resends, since both need identical code/expiry/mail behavior.
     */
    /**
     * @return bool whether the email actually went out. The code/expiry are saved either way —
     *              a transient send failure (confirmed live: Hostinger's own outbound rate limit
     *              briefly rejecting sends during a registration burst) must never surface as an
     *              uncaught 500 straight through register()/resendOtp(); callers instead check
     *              this and tell the visitor to try "resend" again shortly, since the row is
     *              already there waiting regardless of whether this particular send got through.
     */
    public function sendVerificationOtp(): bool
    {
        $this->forceFill([
            'otp_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        try {
            Mail::to($this->email)->send(new OtpVerificationMail($this));

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send verification OTP email.', ['user_id' => $this->id, 'email' => $this->email, 'exception' => $e]);

            return false;
        }
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'otp_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'profile_step_completed_at' => 'datetime',
        ];
    }
}
