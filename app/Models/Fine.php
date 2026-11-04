<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToPradeshiyaSabha;

class Fine extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'member_id',
        'pradeshiya_sabha_id',
        'borrowing_id',
        'amount',
        'issued_date',
        'fine_type',
        'status',
        'paid_date',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_date' => 'date',
        'paid_date' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
