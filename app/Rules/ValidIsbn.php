<?php

namespace App\Rules;

use App\Support\Isbn;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIsbn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Isbn::isValid($value)) {
            $fail('The :attribute must be a valid ISBN-10 or ISBN-13, including its check digit.');
        }
    }
}
