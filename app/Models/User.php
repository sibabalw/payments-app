<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'email_verified_at',
        'onboarding_completed_at',
        'current_business_id',
        'email_preferences',
        'has_completed_dashboard_tour',
        'dashboard_tour_completed_at',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
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
            'onboarding_completed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'email_preferences' => 'array',
            'has_completed_dashboard_tour' => 'boolean',
            'dashboard_tour_completed_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function businesses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedBusinesses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function currentBusiness(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Business::class, 'current_business_id');
    }

    /**
     * Get all businesses the user has access to (owned + associated).
     */
    public function allBusinesses()
    {
        $owned = $this->ownedBusinesses()->get();
        $associated = $this->businesses()->get();

        return $owned->merge($associated)->unique('id');
    }

    /**
     * Get email preferences with defaults.
     */
    public function getEmailPreferences(): array
    {
        $defaults = [
            'welcome' => true,
            'login_notification' => true, // Security notification - enabled by default
            'payment_reminder' => true,
            'payment_success' => true,
            'payment_failed' => true,
            'business_status_changed' => true,
            'business_created' => true,
            'payment_schedule_created' => true,
            'payment_schedule_cancelled' => true,
            'escrow_balance_low' => true,
            'escrow_deposit_confirmed' => true,
        ];

        $preferences = $this->email_preferences ?? [];

        return array_merge($defaults, $preferences);
    }

    /**
     * Check if user should receive a specific email type.
     */
    public function shouldReceiveEmail(string $emailType): bool
    {
        $preferences = $this->getEmailPreferences();

        return $preferences[$emailType] ?? true;
    }

    /**
     * Opt out of a specific email type.
     */
    public function optOut(string $emailType): void
    {
        $preferences = $this->getEmailPreferences();
        $preferences[$emailType] = false;
        $this->update(['email_preferences' => $preferences]);
    }

    /**
     * Opt in to a specific email type.
     */
    public function optIn(string $emailType): void
    {
        $preferences = $this->getEmailPreferences();
        $preferences[$emailType] = true;
        $this->update(['email_preferences' => $preferences]);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
