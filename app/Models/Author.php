<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToPradeshiyaSabha;

class Author extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'name',
        'description',
        'pradeshiya_sabha_id',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
