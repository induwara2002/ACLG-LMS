<?php

namespace App\Services;

use App\Models\Fine;
use App\Models\LibrarySetting;
use App\Models\Member;
use App\Models\Borrowing;
use App\Models\MembershipPayment;
use App\Models\Payment;
use App\Services\PradeshiyaSabhaContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function setting(string $key, string $fallback): string
    {
        return self::normalizeAmount(app(PradeshiyaSabhaSettingsService::class)->value($key, $fallback));
    }

    public function integerSetting(string $key, int $fallback): int
    {
        $value = app(PradeshiyaSabhaSettingsService::class)->value($key, (string) $fallback);
        if (! ctype_digit($value)) {
            throw new InvalidArgumentException('A Sabha-specific library setting is invalid.');
        }

        return (int) $value;
    }

    public function registrationFee(): string
    {
        return $this->setting('registration_fee', '220.00');
    }

    public function monthlyFee(): string
    {
        return $this->setting('monthly_membership_fee', '120.00');
    }

    public function lateRenewalFee(): string
    {
        if (app(PradeshiyaSabhaContext::class)->id() !== null) {
            return self::sumAmounts([
                $this->setting('late_renewal_fee', ''),
                $this->setting('late_renewal_penalty', ''),
            ]);
        }

        return self::fromCents(self::toCents($this->monthlyFee()) + self::toCents($this->setting('late_renewal_charge', '60.00')));
    }

    public function recordMembership(Member $member, string $type, ?string $reference = null): MembershipPayment
    {
        $type = strtoupper($type);
        return app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            function () use ($member, $type, $reference) {
                $amount = match ($type) {
                    'REGISTRATION' => $this->registrationFee(),
                    'MONTHLY_MEMBERSHIP' => $this->monthlyFee(),
                    'LATE_RENEWAL' => $this->lateRenewalFee(),
                    default => throw new InvalidArgumentException('Invalid membership payment type.'),
                };

                return DB::transaction(function () use ($member, $type, $amount, $reference) {
            $member = Member::lockForUpdate()->findOrFail($member->id);

            if ($type === 'REGISTRATION' && $member->membershipPayments()->where('payment_type', 'REGISTRATION')->where('status', 'PAID')->exists()) {
                throw new InvalidArgumentException('Registration payment has already been recorded.');
            }

            if ($reference !== null && trim($reference) !== '') {
                $reference = trim($reference);
                $duplicateReferenceExists = $member->membershipPayments()
                    ->whereNotNull('reference')
                    ->where('reference', $reference)
                    ->where('status', 'PAID')
                    ->exists();

                if ($duplicateReferenceExists) {
                    throw new InvalidArgumentException('A payment with this reference already exists.');
                }
            }

            return $member->membershipPayments()->create([
                'amount' => number_format($amount, 2, '.', ''),
                'payment_type' => $type,
                'payment_date' => Carbon::today(),
                'status' => 'PAID',
                'reference' => $reference,
                'description' => $type === 'REGISTRATION' ? 'One-time registration fee' : 'Membership payment',
            ]);
                });
            }
        );
    }

    public function createFine(Member $member, string $amount, string $type = 'OVERDUE', ?int $borrowingId = null, ?string $description = null): Fine
    {
        $type = strtoupper($type);

        return app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            function () use ($member, $amount, $type, $borrowingId, $description) {
                return DB::transaction(function () use ($member, $amount, $type, $borrowingId, $description) {
                    $member = Member::lockForUpdate()->findOrFail($member->id);
                    if ($borrowingId !== null) {
                        $borrowing = Borrowing::lockForUpdate()->findOrFail($borrowingId);
                        if ((int) $borrowing->member_id !== (int) $member->id
                            || (int) $borrowing->pradeshiya_sabha_id !== (int) $member->pradeshiya_sabha_id) {
                            throw new InvalidArgumentException('The selected borrowing does not belong to this member and Sabha.');
                        }

                        if (Fine::query()
                            ->where('member_id', $member->id)
                            ->where('borrowing_id', $borrowing->id)
                            ->where('fine_type', $type)
                            ->exists()) {
                            throw new InvalidArgumentException('A fine of this type already exists for this borrowing.');
                        }
                    }

                    if ($type === 'OVERDUE' && app(PradeshiyaSabhaContext::class)->id() !== null) {
                        $amount = $this->setting('overdue_fine_amount', '');
                    }

                    $amount = self::normalizeAmount($amount);
                    if (self::toCents($amount) <= 0) {
                        throw new InvalidArgumentException('Fine amount must be greater than zero.');
                    }

                    return $member->fines()->create([
                        'borrowing_id' => $borrowingId,
                        'amount' => $amount,
                        'issued_date' => Carbon::today(),
                        'fine_type' => $type,
                        'status' => 'UNPAID',
                        'description' => $description,
                    ]);
                });
            }
        );
    }

    public function payFine(Fine $fine, ?string $reference = null): Payment
    {
        return app(PradeshiyaSabhaContext::class)->runForId(
            $fine->pradeshiya_sabha_id === null ? null : (int) $fine->pradeshiya_sabha_id,
            fn () => DB::transaction(function () use ($fine, $reference) {
            $fine = Fine::lockForUpdate()->findOrFail($fine->id);
            if ($fine->status === 'PAID') {
                throw new InvalidArgumentException('This fine has already been paid.');
            }
            if ($fine->status === 'WAIVED') {
                throw new InvalidArgumentException('A waived fine cannot be paid.');
            }

            $member = Member::lockForUpdate()->findOrFail($fine->member_id);
            if ((int) $member->pradeshiya_sabha_id !== (int) $fine->pradeshiya_sabha_id) {
                throw new InvalidArgumentException('The fine and member must belong to the same Pradeshiya Sabha.');
            }

            if ($fine->borrowing_id !== null) {
                $borrowing = Borrowing::lockForUpdate()->findOrFail($fine->borrowing_id);
                if ((int) $borrowing->member_id !== (int) $member->id
                    || (int) $borrowing->pradeshiya_sabha_id !== (int) $fine->pradeshiya_sabha_id) {
                    throw new InvalidArgumentException('The fine borrowing does not belong to this member and Sabha.');
                }
            }

            if ($reference !== null && trim($reference) !== '') {
                $reference = trim($reference);
                $duplicateReferenceExists = Payment::whereNotNull('reference_number')
                    ->where('reference_number', $reference)
                    ->where('status', 'PAID')
                    ->exists();

                if ($duplicateReferenceExists) {
                    throw new InvalidArgumentException('A payment with this reference already exists.');
                }
            }

            $paymentType = match ($fine->fine_type) {
                'DAMAGE_COMPENSATION' => 'DAMAGE_COMPENSATION',
                'LOST_BOOK_COMPENSATION' => 'LOST_BOOK_COMPENSATION',
                default => 'OVERDUE_FINE',
            };

            $payment = Payment::create([
                'member_id' => $fine->member_id,
                'borrowing_id' => $fine->borrowing_id,
                'fine_id' => $fine->id,
                'payment_type' => $paymentType,
                'amount' => $fine->amount,
                'payment_method' => 'cash',
                'payment_date' => Carbon::today(),
                'reference_number' => $reference,
                'description' => 'Fine payment',
                'status' => 'PAID',
            ]);

            $fine->update(['status' => 'PAID', 'paid_date' => Carbon::today()]);

            return $payment;
        })
        );
    }

    public function waiveFine(Fine $fine): Fine
    {
        return app(PradeshiyaSabhaContext::class)->runForId(
            $fine->pradeshiya_sabha_id === null ? null : (int) $fine->pradeshiya_sabha_id,
            fn () => DB::transaction(function () use ($fine) {
                $lockedFine = Fine::lockForUpdate()->findOrFail($fine->id);
                if ($lockedFine->status === 'PAID') {
                    throw new InvalidArgumentException('A paid fine cannot be waived.');
                }

                $lockedFine->update(['status' => 'WAIVED']);

                return $lockedFine->refresh();
            })
        );
    }

    public function recordCompensation(Member $member, string $type, string $amount, ?int $borrowingId = null, ?string $reference = null, ?string $description = null): Payment
    {
        $type = strtoupper($type);

        if (! in_array($type, ['DAMAGE_COMPENSATION', 'LOST_BOOK_COMPENSATION'], true)) {
            throw new InvalidArgumentException('Invalid compensation payment type.');
        }

        $amount = self::normalizeAmount($amount);
        if (self::toCents($amount) <= 0) {
            throw new InvalidArgumentException('Compensation amount must be greater than zero.');
        }

        return app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            fn () => DB::transaction(function () use ($member, $type, $amount, $borrowingId, $reference, $description) {
            $member = Member::lockForUpdate()->findOrFail($member->id);

            if ($borrowingId !== null) {
                $borrowing = Borrowing::lockForUpdate()->findOrFail($borrowingId);
                if ((int) $borrowing->member_id !== (int) $member->id
                    || (int) $borrowing->pradeshiya_sabha_id !== (int) $member->pradeshiya_sabha_id) {
                    throw new InvalidArgumentException('The compensation borrowing does not belong to this member and Sabha.');
                }
            }

            if ($reference !== null && trim($reference) !== '') {
                $reference = trim($reference);
                $duplicateReferenceExists = Payment::whereNotNull('reference_number')
                    ->where('reference_number', $reference)
                    ->where('status', 'PAID')
                    ->exists();

                if ($duplicateReferenceExists) {
                    throw new InvalidArgumentException('A payment with this reference already exists.');
                }
            }

            if ($borrowingId !== null) {
                $duplicateCompensationExists = Payment::where('member_id', $member->id)
                    ->where('borrowing_id', $borrowingId)
                    ->where('payment_type', $type)
                    ->where('status', 'PAID')
                    ->exists();

                if ($duplicateCompensationExists) {
                    throw new InvalidArgumentException('Compensation has already been paid for this borrowing.');
                }
            }

            return Payment::create([
                'member_id' => $member->id,
                'borrowing_id' => $borrowingId,
                'payment_type' => $type,
                'amount' => $amount,
                'payment_method' => 'cash',
                'payment_date' => Carbon::today(),
                'reference_number' => $reference,
                'description' => $description ?? 'Compensation payment',
                'status' => 'PAID',
            ]);
        })
        );
    }

    public function outstandingBalance(Member $member): string
    {
        return self::normalizeAmount((string) $member->fines()->where('status', 'UNPAID')->sum('amount'));
    }

    public static function normalizeAmount(string $amount): string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a valid decimal value with up to two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole).'.'.str_pad($fraction, 2, '0');
    }

    public static function toCents(string $amount): int
    {
        $normalized = self::normalizeAmount($amount);
        [$whole, $fraction] = explode('.', $normalized);

        return ((int) $whole * 100) + (int) $fraction;
    }

    public static function fromCents(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function sumAmounts(iterable $amounts): string
    {
        $cents = 0;
        foreach ($amounts as $amount) {
            $cents += self::toCents((string) $amount);
        }

        return self::fromCents($cents);
    }
}
