<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToPradeshiyaSabha;

class Renewal extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $casts = [
        'old_due_date' => 'date',
        'new_due_date' => 'date',
        'renewal_date' => 'date',
    ];

    protected $fillable = [
        'borrowing_id',
        'pradeshiya_sabha_id',
        'old_due_date',
        'new_due_date',
        'renewal_date',
        'reason',
    ];

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }
}
