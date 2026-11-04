<?php

namespace Tests\Unit;

use App\Support\Isbn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IsbnTest extends TestCase
{
    #[DataProvider('validIsbns')]
    public function test_it_accepts_valid_isbn_10_and_isbn_13_values(string $isbn): void
    {
        $this->assertTrue(Isbn::isValid($isbn));
    }

    #[DataProvider('invalidIsbns')]
    public function test_it_rejects_invalid_isbn_values(string $isbn): void
    {
        $this->assertFalse(Isbn::isValid($isbn));
    }

    public function test_it_normalizes_spaces_hyphens_and_isbn_10_check_digit_case(): void
    {
        $this->assertSame('0306406152', Isbn::normalize('0-306-40615-2'));
        $this->assertSame('080442957X', Isbn::normalize('0 8044 2957 x'));
        $this->assertTrue(Isbn::isValid('0 8044 2957 x'));
    }

    public static function validIsbns(): array
    {
        return [
            ['0306406152'],
            ['080442957X'],
            ['9780306406157'],
            ['9791090636071'],
        ];
    }

    public static function invalidIsbns(): array
    {
        return [
            ['0306406153'],
            ['9780306406158'],
            ['1234567890'],
            ['4006381333931'],
            ['978-030640615'],
            ['97803064061A7'],
        ];
    }
}
