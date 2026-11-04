<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPradeshiyaSabha;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookReservation extends Model
{
    use BelongsToPradeshiyaSabha;
    use HasFactory;

    protected $fillable = [
        'pradeshiya_sabha_id',
        'member_id',
        'book_id',
        'status',
        'reserved_at',
    ];

    protected $casts = ['reserved_at' => 'datetime'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}