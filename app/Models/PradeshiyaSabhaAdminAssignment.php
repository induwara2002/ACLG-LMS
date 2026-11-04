<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PradeshiyaSabhaAdminAssignment extends Model
{
    protected $fillable = ['pradeshiya_sabha_id', 'user_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function pradeshiyaSabha(): BelongsTo
    {
        return $this->belongsTo(PradeshiyaSabha::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}