<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPradeshiyaSabha;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToPradeshiyaSabha;
    use HasFactory;

    public const MAIN_DDC_RANGES = [
        '000' => '000-100',
        '100' => '100-200',
        '300' => '300-400',
        '500' => '500-600',
        '700' => '700-800',
        '900' => '900-1000',
    ];

    public const BOOK_REGISTRATION_CATEGORIES = [
        '000' => ['label' => '000-100', 'name' => 'Generalities'],
        '100' => ['label' => '100-200', 'name' => 'Philosophy and Psychology'],
        '300' => ['label' => '300-400', 'name' => 'Social Sciences'],
        '500' => ['label' => '500-600', 'name' => 'Science'],
        '700' => ['label' => '700-800', 'name' => 'Art'],
        '900' => ['label' => '900-1000', 'name' => 'History and Geography'],
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'pradeshiya_sabha_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (empty($category->code)) {
                $category->code = 'CAT-'.str_pad((string) ((int) self::max('id') + 1), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
