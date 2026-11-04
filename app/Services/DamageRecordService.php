<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\DamageRecord;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DamageRecordService
{
    public function reportDamage(Member $member, Borrowing $borrowing, BookCopy $bookCopy, string $damageType, string $damageDate, string $compensationAmount, string $description, ?User $createdBy = null): DamageRecord
    {
        $damageType = strtoupper($damageType);

        if (! in_array($damageType, ['MINOR', 'MAJOR'], true)) {
            throw new InvalidArgumentException('Damage type must be MINOR or MAJOR.');
        }

        $compensationAmount = PaymentService::normalizeAmount($compensationAmount);
        if (PaymentService::toCents($compensationAmount) <= 0) {
            throw new InvalidArgumentException('Compensation amount must be greater than zero.');
        }

        if ($damageType === 'MINOR' && PaymentService::toCents($compensationAmount) < 500000) {
            throw new InvalidArgumentException('Minor damage compensation must be at least Rs. 5,000.');
        }

        return DB::transaction(function () use ($member, $borrowing, $bookCopy, $damageType, $damageDate, $compensationAmount, $description, $createdBy) {
            $member = Member::lockForUpdate()->findOrFail($member->id);
            $borrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);
            $bookCopy = BookCopy::lockForUpdate()->findOrFail($bookCopy->id);

            if ($borrowing->member_id !== $member->id) {
                throw new InvalidArgumentException('The selected borrowing does not belong to this member.');
            }

            if ((int) $borrowing->book_copy_id !== (int) $bookCopy->id) {
                throw new InvalidArgumentException('The selected book copy does not match the borrowing.');
            }

            $borrowingStatus = strtoupper((string) $borrowing->status);
            $copyStatus = strtoupper((string) $bookCopy->status);

            if ($borrowing->return_date !== null || $borrowingStatus === 'RETURNED') {
                throw new InvalidArgumentException('A returned borrowing cannot be marked damaged.');
            }

            if (in_array($borrowingStatus, ['LOST', 'DAMAGED'], true)
                || in_array($copyStatus, ['LOST', 'DAMAGED', 'REPAIR'], true)) {
                throw new InvalidArgumentException('A lost or damaged borrowing cannot be reported as damaged.');
            }

            if (DamageRecord::where('borrowing_id', $borrowing->id)
                ->where('book_copy_id', $bookCopy->id)
                ->whereIn('status', ['REPORTED', 'UNDER_REPAIR', 'REPAIRED'])
                ->exists()) {
                throw new InvalidArgumentException('This borrowing already has an active damage record.');
            }

            $damageRecord = DamageRecord::create([
                'member_id' => $member->id,
                'borrowing_id' => $borrowing->id,
                'book_id' => $borrowing->book_id,
                'book_copy_id' => $bookCopy->id,
                'damage_type' => $damageType,
                'damage_date' => Carbon::parse($damageDate)->toDateString(),
                'description' => $description,
                'repair_cost' => $compensationAmount,
                'compensation_amount' => $compensationAmount,
                'status' => 'REPORTED',
                'payment_status' => 'UNPAID',
                'created_by' => $createdBy?->id,
            ]);

            $bookCopy->update(['status' => 'DAMAGED']);
            $borrowing->update(['status' => 'DAMAGED']);

            return $damageRecord;
        });
    }

    public function payCompensation(DamageRecord $damageRecord, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($damageRecord, $reference) {
            $lockedDamageRecord = DamageRecord::lockForUpdate()->findOrFail($damageRecord->id);

            if ($lockedDamageRecord->payment_status === 'PAID') {
                throw new InvalidArgumentException('This damage compensation has already been paid.');
            }

            $member = Member::lockForUpdate()->findOrFail($lockedDamageRecord->member_id);
            $payment = app(PaymentService::class)->recordCompensation(
                $member,
                'DAMAGE_COMPENSATION',
                (string) $lockedDamageRecord->compensation_amount,
                $lockedDamageRecord->borrowing_id,
                $reference,
                'Damage compensation'
            );

            $lockedDamageRecord->update([
                'payment_status' => 'PAID',
                'status' => 'REPAIRED',
            ]);

            return $payment;
        });
    }
}
