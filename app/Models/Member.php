<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\BookReservation;
use App\Models\Concerns\BelongsToPradeshiyaSabha;
use App\Models\Scopes\PradeshiyaSabhaScope;
use Carbon\Carbon;

class Member extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'user_id',
        'pradeshiya_sabha_id',
        'member_id',
        'membership_type_id',
        'full_name',
        'email',
        'nic',
        'phone',
        'address',
        'registration_fee',
        'registration_date',
        'membership_start_date',
        'next_renewal_date',
        'expiry_date',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'membership_start_date' => 'date',
        'next_renewal_date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function effectiveStatus(): string
    {
        $status = strtoupper((string) $this->status);

        if (in_array($status, ['PENDING', 'REJECTED', 'SUSPENDED'], true)) {
            return $status;
        }

        $expiryDate = $this->effectiveExpiryDate();

        return $expiryDate && Carbon::today()->gt($expiryDate)
            ? 'EXPIRED'
            : $status;
    }

    public function portalStatus(): string
    {
        return strtoupper((string) $this->status) === 'PENDING'
            ? 'PENDING'
            : $this->effectiveStatus();
    }

    public function effectiveExpiryDate(): ?Carbon
    {
        if ($this->expiry_date) {
            return $this->expiry_date->copy();
        }

        $membershipStart = $this->membership_start_date ?? $this->registration_date;

        return $membershipStart?->copy()->addYear();
    }

    protected static function booted(): void
    {
        static::creating(function (self $member) {
            if (empty($member->member_id)) {
                $sequence = self::withoutGlobalScope(PradeshiyaSabhaScope::class)->max('id') + 1;
                $member->member_id = 'LIB-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class);
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BookReservation::class);
    }

    public function membershipPayments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function outstandingFines(): HasMany
    {
        return $this->hasMany(Fine::class)->where('status', 'UNPAID');
    }

    public function damageRecords(): HasMany
    {
        return $this->hasMany(DamageRecord::class);
    }

    public function lostBooks(): HasMany
    {
        return $this->hasMany(LostBook::class);
    }
}
