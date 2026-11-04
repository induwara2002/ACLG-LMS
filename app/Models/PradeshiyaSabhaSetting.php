<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PradeshiyaSabhaSetting extends Model
{
    protected $fillable = ['pradeshiya_sabha_id', 'key', 'value', 'description'];

    public function pradeshiyaSabha(): BelongsTo
    {
        return $this->belongsTo(PradeshiyaSabha::class);
    }
}