<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PradeshiyaSabha extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'logo_path', 'address', 'phone', 'email', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::created(function (self $sabha): void {
            app(\App\Services\DdcRangeSynchronizer::class)->syncFor($sabha);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    public function publishers(): HasMany
    {
        return $this->hasMany(Publisher::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function bookCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function lostBooks(): HasMany
    {
        return $this->hasMany(LostBook::class);
    }

    public function damageRecords(): HasMany
    {
        return $this->hasMany(DamageRecord::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }

    public function adminAssignment(): HasOne
    {
        return $this->hasOne(PradeshiyaSabhaAdminAssignment::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(PradeshiyaSabhaSetting::class);
    }

    public function setting(string $key): ?string
    {
        return $this->settings()->where('key', $key)->value('value');
    }
}
