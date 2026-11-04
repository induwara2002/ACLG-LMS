<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\BelongsToPradeshiyaSabha;
use Carbon\Carbon;

class Borrowing extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'member_id',
        'pradeshiya_sabha_id',
        'book_id',
        'book_copy_id',
        'issue_date',
        'issue_at',
        'due_date',
        'return_date',
        'returned_at',
        'status',
        'renewal_count',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'issue_at' => 'datetime',
        'due_date' => 'date',
        'return_date' => 'date',
        'returned_at' => 'datetime',
        'renewal_count' => 'integer',
    ];

    public function effectiveStatus(): string
    {
        if ($this->return_date) {
            return 'RETURNED';
        }

        if ($this->due_date && now()->startOfDay()->gt($this->due_date)) {
            return 'OVERDUE';
        }

        return strtoupper((string) $this->status);
    }

    public function wasOverdue(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        $completionDate = $this->return_date ?? Carbon::today();

        return $completionDate->gt($this->due_date);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('return_date')->whereIn('status', ['ISSUED', 'OVERDUE', 'issued', 'overdue']);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }

    public function lostBook(): HasOne
    {
        return $this->hasOne(LostBook::class);
    }

    public function damageRecords(): HasMany
    {
        return $this->hasMany(DamageRecord::class);
    }
}
