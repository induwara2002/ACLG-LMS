<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToPradeshiyaSabha;

class Payment extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'member_id',
        'pradeshiya_sabha_id',
        'borrowing_id',
        'fine_id',
        'payment_type',
        'amount',
        'payment_method',
        'payment_date',
        'reference_number',
        'description',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function fine(): BelongsTo
    {
        return $this->belongsTo(Fine::class);
    }
}
