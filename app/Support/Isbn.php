<?php

namespace App\Support;

class Isbn
{
    public static function normalize(string $isbn): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', trim($isbn)));
    }

    public static function isValid(string $isbn): bool
    {
        $isbn = self::normalize($isbn);

        if (preg_match('/\A[0-9]{9}[0-9X]\z/', $isbn) === 1) {
            $sum = 0;
            for ($index = 0; $index < 10; $index++) {
                $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
                $sum += $digit * (10 - $index);
            }

            return $sum % 11 === 0;
        }

        if (preg_match('/\A(?:978|979)[0-9]{10}\z/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += (int) $isbn[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10 === (int) $isbn[12];
    }
}
