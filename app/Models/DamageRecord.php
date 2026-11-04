<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToPradeshiyaSabha;

class DamageRecord extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'member_id',
        'pradeshiya_sabha_id',
        'borrowing_id',
        'book_id',
        'book_copy_id',
        'damage_type',
        'damage_date',
        'description',
        'repair_cost',
        'compensation_amount',
        'status',
        'payment_status',
        'created_by',
    ];

    protected $casts = [
        'damage_date' => 'date',
        'repair_cost' => 'decimal:2',
        'compensation_amount' => 'decimal:2',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
