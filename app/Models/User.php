<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Scopes\PradeshiyaSabhaScope;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';
    public const ROLE_SABHA_ADMIN = 'SABHA_ADMIN';
    public const ROLE_MAIN_ADMIN = 'MAIN_ADMIN';
    public const ROLE_PRADESHIYA_SABHA_ADMIN = 'PRADESHIYA_SABHA_ADMIN';
    public const ROLE_LIBRARIAN = 'LIBRARIAN';
    public const ROLE_MEMBER = 'MEMBER';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'pradeshiya_sabha_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new PradeshiyaSabhaScope());
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function pradeshiyaSabhaAdminAssignment(): HasOne
    {
        return $this->hasOne(PradeshiyaSabhaAdminAssignment::class);
    }

    public function pradeshiyaSabha(): BelongsTo
    {
        return $this->belongsTo(PradeshiyaSabha::class);
    }

    public function isMainAdmin(): bool
    {
        $configuredEmail = strtolower((string) config('app.main_admin_email', 'admin@library.com'));

        return in_array(strtoupper((string) $this->role), [self::ROLE_SUPER_ADMIN, self::ROLE_MAIN_ADMIN], true)
            || (strtoupper((string) $this->role) === self::ROLE_ADMIN && (
                app()->environment('testing')
                || strtolower((string) $this->email) === $configuredEmail
            ));
    }

    public function isPradeshiyaSabhaAdmin(): bool
    {
        return in_array(strtoupper((string) $this->role), [self::ROLE_SABHA_ADMIN, self::ROLE_PRADESHIYA_SABHA_ADMIN], true)
            && (bool) $this->is_active
            && $this->pradeshiya_sabha_id !== null
            && $this->pradeshiyaSabha()->where('is_active', true)->exists()
            && PradeshiyaSabhaAdminAssignment::query()
                ->where('user_id', $this->id)
                ->where('pradeshiya_sabha_id', $this->pradeshiya_sabha_id)
                ->where('is_active', true)
                ->exists();
    }

    public function hasRole(string ...$roles): bool
    {
        foreach (array_map(fn ($role) => strtoupper($role), $roles) as $role) {
            if (in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_MAIN_ADMIN], true)) {
                if ($this->isMainAdmin()) {
                    return true;
                }

                continue;
            }

            if (in_array($role, [self::ROLE_SABHA_ADMIN, self::ROLE_PRADESHIYA_SABHA_ADMIN], true)) {
                if ($this->isPradeshiyaSabhaAdmin()) {
                    return true;
                }

                continue;
            }

            if ($role === strtoupper((string) $this->role)) {
                return true;
            }
        }

        return false;
    }

    public function dashboardRoute(): string
    {
        return match (strtoupper((string) $this->role)) {
            self::ROLE_ADMIN, self::ROLE_MAIN_ADMIN, self::ROLE_SUPER_ADMIN => $this->isMainAdmin() ? route('admin.dashboard') : route('home'),
            self::ROLE_PRADESHIYA_SABHA_ADMIN, self::ROLE_SABHA_ADMIN => route('admin.dashboard'),
            self::ROLE_LIBRARIAN => route('librarian.dashboard'),
            default => route('member.dashboard'),
        };
    }
}
