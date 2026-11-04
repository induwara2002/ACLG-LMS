<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToPradeshiyaSabha;

class LostBook extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'member_id',
        'pradeshiya_sabha_id',
        'borrowing_id',
        'book_id',
        'book_copy_id',
        'lost_date',
        'replacement_cost',
        'compensation_amount',
        'status',
        'payment_status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'lost_date' => 'date',
        'replacement_cost' => 'decimal:2',
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
