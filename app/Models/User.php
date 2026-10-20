<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const AUTH_VERSION_SESSION_KEY = 'auth_version_web';

    protected $fillable = [
        'company_id',
        'role_id',
        'is_company_owner',
        'plan_restricted',
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'auth_version',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_company_owner' => 'boolean',
            'plan_restricted' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['auth_version' => $this->authenticationVersion()];
    }

    public function authenticationVersion(): string
    {
        return $this->auth_version ?? '0';
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('status')) {
                // Persist status and revocation together in the same UPDATE.
                // A nonce avoids reusing versions when stale instances are saved.
                $user->auth_version = (string) Str::uuid();
                $user->setRememberToken(Str::random(60));
            }
        });
    }
}
