<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\LostBook;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LostBookService
{
    public function reportLost(Member $member, Borrowing $borrowing, BookCopy $bookCopy, string $lostDate, string $compensationAmount, ?string $notes = null, ?User $createdBy = null): LostBook
    {
        return DB::transaction(function () use ($member, $borrowing, $bookCopy, $lostDate, $compensationAmount, $notes, $createdBy) {
            $member = Member::lockForUpdate()->findOrFail($member->id);
            $borrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);
            $bookCopy = BookCopy::lockForUpdate()->findOrFail($bookCopy->id);

            if ($borrowing->member_id !== $member->id) {
                throw new InvalidArgumentException('The selected borrowing does not belong to this member.');
            }

            if ((int) $borrowing->book_copy_id !== (int) $bookCopy->id) {
                throw new InvalidArgumentException('The selected book copy does not match the borrowing.');
            }

            if ($borrowing->return_date !== null || strtoupper((string) $borrowing->status) === 'RETURNED') {
                throw new InvalidArgumentException('A returned borrowing cannot be marked lost.');
            }

            $borrowingStatus = strtoupper((string) $borrowing->status);
            $copyStatus = strtoupper((string) $bookCopy->status);

            if ($copyStatus === 'LOST') {
                throw new InvalidArgumentException('This copy has already been reported as lost.');
            }

            if (in_array($borrowingStatus, ['LOST', 'DAMAGED'], true)
                || in_array($copyStatus, ['DAMAGED', 'REPAIR'], true)) {
                throw new InvalidArgumentException('A lost or damaged borrowing cannot be marked lost.');
            }

            if (LostBook::where('borrowing_id', $borrowing->id)->exists()) {
                throw new InvalidArgumentException('This borrowing already has a lost-book record.');
            }

            $compensationAmount = PaymentService::normalizeAmount($compensationAmount);
            if (PaymentService::toCents($compensationAmount) <= 0) {
                throw new InvalidArgumentException('Compensation amount must be greater than zero.');
            }

            $lostBook = LostBook::create([
                'member_id' => $member->id,
                'borrowing_id' => $borrowing->id,
                'book_id' => $borrowing->book_id,
                'book_copy_id' => $bookCopy->id,
                'lost_date' => Carbon::parse($lostDate)->toDateString(),
                'replacement_cost' => $compensationAmount,
                'compensation_amount' => $compensationAmount,
                'status' => 'REPORTED',
                'payment_status' => 'UNPAID',
                'notes' => $notes,
                'created_by' => $createdBy?->id,
            ]);

            $borrowing->update(['status' => 'LOST']);
            $bookCopy->update(['status' => 'LOST']);

            return $lostBook;
        });
    }

    public function payCompensation(LostBook $lostBook, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($lostBook, $reference) {
            $lockedLostBook = LostBook::lockForUpdate()->findOrFail($lostBook->id);

            if ($lockedLostBook->payment_status === 'PAID') {
                throw new InvalidArgumentException('This lost-book compensation has already been paid.');
            }

            $member = Member::lockForUpdate()->findOrFail($lockedLostBook->member_id);
            $payment = app(PaymentService::class)->recordCompensation(
                $member,
                'LOST_BOOK_COMPENSATION',
                (string) $lockedLostBook->compensation_amount,
                $lockedLostBook->borrowing_id,
                $reference,
                'Lost book compensation'
            );

            $lockedLostBook->update([
                'payment_status' => 'PAID',
                'status' => 'RESOLVED',
            ]);

            return $payment;
        });
    }
}
