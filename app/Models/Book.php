<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPradeshiyaSabha;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;
    use BelongsToPradeshiyaSabha;

    protected $fillable = [
        'book_id',
        'pradeshiya_sabha_id',

        'title',

        'isbn',

        'edition',

        'author_id',

        'publisher_id',

        'category_id',

        'publication_year',

        'language',

        'ddc_number',

        'shelf_number',

        'description',

        'cover_image',

        'status',
    ];

    public static function isSafeCoverImagePath(?string $path): bool
    {
        return is_string($path)
            && preg_match('~\Abook-covers/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)\z~iD', $path) === 1;
    }

    public function getCoverImageUrlAttribute(): string
    {
        if (self::isSafeCoverImagePath($this->cover_image)
            && Storage::disk('public')->exists($this->cover_image)) {
            return Storage::disk('public')->url($this->cover_image);
        }

        return asset('images/book-placeholder.svg');
    }

    public function setStatusAttribute($value): void
    {
        $normalized = strtolower((string) $value);

        $mapping = [
            'active' => 'available',

            'inactive' => 'unavailable',

            'archived' => 'archived',

            'reserved' => 'reserved',

            'available' => 'available',
            
            'unavailable' => 'unavailable',
        ];

        $this->attributes['status'] = $mapping[$normalized] ?? $normalized;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(BookInventory::class);
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function lostBooks(): HasMany
    {
        return $this->hasMany(LostBook::class);
    }
}
