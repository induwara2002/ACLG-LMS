<?php

namespace App\Models;

use App\Services\PradeshiyaSabhaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class MembershipType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'max_books',
        'description',
    ];

    public function scopeAssignable(Builder $query): Builder
    {
        return $query->whereRaw('upper(name) in (?, ?)', ['CHILDREN', 'ELDERS']);
    }

    public function getMaxBooksAttribute($value): int
    {
        $sabhaId = app(PradeshiyaSabhaContext::class)->id();
        if ($sabhaId === null) {
            return (int) $value;
        }

        $key = match (strtoupper((string) $this->name)) {
            'CHILDREN', 'INDIVIDUAL' => 'individual_borrowing_limit',
            'ELDERS', 'FAMILY' => 'family_borrowing_limit',
            default => null,
        };

        if ($key === null) {
            return (int) $value;
        }

        $limit = PradeshiyaSabhaSetting::query()
            ->where('pradeshiya_sabha_id', $sabhaId)
            ->where('key', $key)
            ->value('value');

        if ($limit === null || trim((string) $limit) === '') {
            return (int) $value;
        }

        if (! ctype_digit((string) $limit) || (int) $limit < 1) {
            throw new InvalidArgumentException('The Sabha-specific borrowing limit is invalid.');
        }

        return (int) $limit;
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
