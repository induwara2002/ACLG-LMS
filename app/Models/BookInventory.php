<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPradeshiyaSabha;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookInventory extends Model
{
    use BelongsToPradeshiyaSabha;
    use HasFactory;

    protected $fillable = [
        'pradeshiya_sabha_id',
        'book_id',
        'total_quantity',
        'available_quantity',
        'shelf_location',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'available_quantity' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}