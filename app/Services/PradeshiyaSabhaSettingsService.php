<?php

namespace App\Services;

use App\Exceptions\PradeshiyaSabhaConfigurationIncompleteException;
use App\Models\LibrarySetting;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaSetting;
use InvalidArgumentException;

class PradeshiyaSabhaSettingsService
{
    public const REQUIRED_SETTINGS = [
        'registration_fee',
        'monthly_membership_fee',
        'late_renewal_fee',
        'late_renewal_penalty',
        'overdue_fine_amount',
        'membership_duration_months',
        'individual_borrowing_limit',
        'family_borrowing_limit',
        'issue_period_days',
        'maximum_renewals',
        'currency',
        'timezone',
    ];

    public const OPTIONAL_SETTINGS = [
        'name_si',
        'library_name',
        'library_address',
        'contact_number',
        'library_email',
        'opening_hours',
    ];

    private const MONETARY_SETTINGS = [
        'registration_fee',
        'monthly_membership_fee',
        'late_renewal_fee',
        'late_renewal_penalty',
        'overdue_fine_amount',
    ];

    private const POSITIVE_INTEGER_SETTINGS = [
        'membership_duration_months',
        'individual_borrowing_limit',
        'family_borrowing_limit',
        'issue_period_days',
    ];

    public function configurationStatus(PradeshiyaSabha $sabha): array
    {
        $values = $sabha->settings()
            ->whereIn('key', self::REQUIRED_SETTINGS)
            ->pluck('value', 'key');
        $missing = [];
        $invalid = [];

        foreach (self::REQUIRED_SETTINGS as $key) {
            $value = $values->get($key);
            if (! is_string($value) || trim($value) === '') {
                $missing[] = $key;

                continue;
            }

            if (! $this->isValidValue($key, $value)) {
                $invalid[] = $key;
            }
        }

        return [
            'complete' => $missing === [] && $invalid === [],
            'missing' => $missing,
            'invalid' => $invalid,
        ];
    }

    public function assertConfigured(PradeshiyaSabha $sabha): void
    {
        $status = $this->configurationStatus($sabha);
        if (! $status['complete']) {
            $details = [];
            if ($status['missing'] !== []) {
                $details[] = 'Missing settings: '.implode(', ', $status['missing']).'.';
            }
            if ($status['invalid'] !== []) {
                $details[] = 'Invalid settings: '.implode(', ', $status['invalid']).'.';
            }

            throw new PradeshiyaSabhaConfigurationIncompleteException(
                'Please configure this Pradeshiya Sabha\'s library settings before accepting registrations. '
                .implode(' ', $details)
            );
        }
    }

    public function isValidCurrencyCode(string $value): bool
    {
        if (preg_match('/^[A-Z]{3}$/', $value) !== 1) {
            return false;
        }

        $bundle = \ResourceBundle::create('en_US', 'ICUDATA-curr');
        $currencies = $bundle?->get('Currencies');

        return $currencies instanceof \ResourceBundle && $currencies->get($value) !== null;
    }

    public function value(string $key, ?string $legacyFallback = null): string
    {
        $sabhaId = app(PradeshiyaSabhaContext::class)->id();

        if ($sabhaId !== null) {
            $value = PradeshiyaSabhaSetting::query()
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->where('key', $key)
                ->value('value');

            if ($value === null || trim((string) $value) === '') {
                if ($legacyFallback !== null) {
                    return $legacyFallback;
                }

                throw new InvalidArgumentException("The Sabha-specific library setting '{$key}' is not configured.");
            }

            if (! $this->isValidValue($key, (string) $value)) {
                throw new InvalidArgumentException("The Sabha-specific library setting '{$key}' is invalid.");
            }

            return trim((string) $value);
        }

        $value = LibrarySetting::query()->where('key', $key)->value('value');

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if ($legacyFallback !== null) {
            return $legacyFallback;
        }

        throw new InvalidArgumentException("The library setting '{$key}' is not configured.");
    }

    private function isValidValue(string $key, string $value): bool
    {
        $value = trim($value);

        if (in_array($key, self::MONETARY_SETTINGS, true)) {
            return preg_match('/^\d+(?:\.\d{1,2})?$/', $value) === 1;
        }

        if (in_array($key, self::POSITIVE_INTEGER_SETTINGS, true)) {
            return ctype_digit($value) && (int) $value > 0;
        }

        if ($key === 'maximum_renewals') {
            return ctype_digit($value);
        }

        if ($key === 'currency') {
            return $this->isValidCurrencyCode($value);
        }

        if ($key === 'timezone') {
            return in_array($value, timezone_identifiers_list(), true);
        }

        return false;
    }
}
