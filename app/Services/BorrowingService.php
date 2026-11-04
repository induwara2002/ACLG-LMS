<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BorrowingService
{
    public const ISSUE_DAYS = 14;
    public const MAX_RENEWALS = 2;

    public function issue(Member $member, Book $book): Borrowing
    {
        if ((int) $member->pradeshiya_sabha_id !== (int) $book->pradeshiya_sabha_id) {
            throw new InvalidArgumentException('The member and book must belong to the same Pradeshiya Sabha.');
        }

        $borrowing = app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            fn () => DB::transaction(function () use ($member, $book) {
            $member = Member::with(['membershipType', 'user'])->lockForUpdate()->findOrFail($member->id);
            $book = Book::lockForUpdate()->findOrFail($book->id);

            if (! $member->user?->is_active || $member->effectiveStatus() !== 'ACTIVE') {
                throw new InvalidArgumentException('Member membership is not active or has expired.');
            }

            $limit = (int) ($member->membershipType?->max_books ?? 0);
            $activeBorrowings = $member->borrowings()->active()->count();
            if ($limit < 1 || $activeBorrowings >= $limit) {
                throw new InvalidArgumentException('Member borrowing limit has been reached.');
            }

            $copy = $book->copies()
                ->where('pradeshiya_sabha_id', $member->pradeshiya_sabha_id)
                ->where('status', 'AVAILABLE')
                ->lockForUpdate()
                ->first();

            if (! $copy) {
                throw new InvalidArgumentException('No available copy of this book.');
            }

            if ((int) $copy->pradeshiya_sabha_id !== (int) $member->pradeshiya_sabha_id) {
                throw new InvalidArgumentException('The selected copy does not belong to the member\'s Pradeshiya Sabha.');
            }

            if ($copy->borrowings()->whereNull('return_date')->exists()) {
                throw new InvalidArgumentException('This physical copy already has an active borrowing.');
            }

            $issuedAt = Carbon::now();
            $issueDate = $issuedAt->copy()->startOfDay();
            $issueDays = app(PaymentService::class)->integerSetting('issue_period_days', self::ISSUE_DAYS);
            $borrowing = $book->borrowings()->create([
                'member_id' => $member->id,
                'book_copy_id' => $copy->id,
                'issue_date' => $issueDate->toDateString(),
                'issue_at' => $issuedAt,
                'due_date' => $issueDate->copy()->addDays($issueDays)->toDateString(),
                'status' => 'ISSUED',
                'renewal_count' => 0,
            ]);

            $copy->update(['status' => 'ISSUED']);

            return $borrowing->load(['member.membershipType', 'book', 'bookCopy', 'pradeshiyaSabha']);
        })
        );

        app(EmailAlertService::class)->sendBookIssue($borrowing);

        return $borrowing;
    }

    public function markOverdue(Borrowing $borrowing): Borrowing
    {
        if ($borrowing->return_date === null && $borrowing->due_date->isBefore(Carbon::today())) {
            $borrowing->update(['status' => 'OVERDUE']);
        }

        return $borrowing->refresh();
    }

    public function syncOverdue(): void
    {
        if (auth()->user()?->isMainAdmin()) {
            return;
        }

        Borrowing::whereNull('return_date')
            ->where('status', 'ISSUED')
            ->whereDate('due_date', '<', Carbon::today())
            ->update(['status' => 'OVERDUE']);
    }

    public function returnBook(Borrowing $borrowing): Borrowing
    {
        $context = app(PradeshiyaSabhaContext::class);
        $user = auth()->user();
        $borrowingSabhaId = $borrowing->pradeshiya_sabha_id;
        $currentSabhaId = $context->id();

        if ($user && ! $user->isMainAdmin() && (int) $currentSabhaId !== (int) $borrowingSabhaId) {
            throw new InvalidArgumentException('The borrowing does not belong to this Pradeshiya Sabha.');
        }

        return $context->runForId(
            $borrowingSabhaId === null ? null : (int) $borrowingSabhaId,
            fn () => DB::transaction(function () use ($borrowing) {
            $lockedBorrowing = Borrowing::with('member')->lockForUpdate()->findOrFail($borrowing->id);

            $borrowingStatus = strtoupper((string) $lockedBorrowing->status);

            if ($lockedBorrowing->return_date !== null || $borrowingStatus === 'RETURNED') {
                throw new InvalidArgumentException('This book has already been returned.');
            }

            $copy = $lockedBorrowing->bookCopy()->lockForUpdate()->first();
            if (! $copy || (int) $copy->book_id !== (int) $lockedBorrowing->book_id) {
                throw new InvalidArgumentException('The physical copy is not valid for this borrowing.');
            }

            if (in_array($borrowingStatus, ['LOST', 'DAMAGED'], true) || in_array(strtoupper((string) $copy->status), ['LOST', 'DAMAGED', 'REPAIR'], true)) {
                throw new InvalidArgumentException('A lost or damaged borrowing cannot be returned normally.');
            }

            if (! in_array($borrowingStatus, ['ISSUED', 'OVERDUE'], true)) {
                throw new InvalidArgumentException('Only active borrowings can be returned.');
            }

            if (! in_array(strtoupper((string) $copy->status), ['ISSUED', 'OVERDUE'], true)) {
                throw new InvalidArgumentException('The physical copy is not currently issued.');
            }

            if (! $lockedBorrowing->member
                || (int) $lockedBorrowing->member->pradeshiya_sabha_id !== (int) $lockedBorrowing->pradeshiya_sabha_id
                || (int) $copy->pradeshiya_sabha_id !== (int) $lockedBorrowing->pradeshiya_sabha_id) {
                throw new InvalidArgumentException('The borrowing, member, and copy must belong to the same Pradeshiya Sabha.');
            }

            $returnedAt = Carbon::now();
            $lockedBorrowing->update([
                'return_date' => $returnedAt->toDateString(),
                'returned_at' => $returnedAt,
                'status' => 'RETURNED',
            ]);
            $copy->update(['status' => 'AVAILABLE']);

            return $lockedBorrowing->load(['member', 'book', 'bookCopy', 'renewals']);
        })
        );
    }

    public function renewBook(Borrowing $borrowing): Borrowing
    {
        $user = auth()->user();
        $currentSabhaId = app(PradeshiyaSabhaContext::class)->id();
        $borrowingSabhaId = $borrowing->pradeshiya_sabha_id;
        $belongsToCurrentSabha = $currentSabhaId === null
            ? $borrowingSabhaId === null
            : (int) $currentSabhaId === (int) $borrowingSabhaId;

        if (! $user?->isMainAdmin() && ! $belongsToCurrentSabha) {
            throw new InvalidArgumentException('The borrowing does not belong to this Pradeshiya Sabha.');
        }

        return app(PradeshiyaSabhaContext::class)->runForId(
            $borrowingSabhaId === null ? null : (int) $borrowingSabhaId,
            fn () => DB::transaction(function () use ($borrowing) {
            $lockedBorrowing = Borrowing::with('member.membershipType', 'member.user')->lockForUpdate()->findOrFail($borrowing->id);

            $borrowingStatus = strtoupper((string) $lockedBorrowing->status);

            if ($lockedBorrowing->return_date !== null || $borrowingStatus === 'RETURNED') {
                throw new InvalidArgumentException('A returned borrowing cannot be renewed.');
            }

            $copy = $lockedBorrowing->bookCopy()->lockForUpdate()->first();
            if (! $copy || (int) $copy->book_id !== (int) $lockedBorrowing->book_id) {
                throw new InvalidArgumentException('The physical copy is not valid for this borrowing.');
            }

            if (in_array($borrowingStatus, ['LOST', 'DAMAGED'], true) || in_array(strtoupper((string) $copy->status), ['LOST', 'DAMAGED', 'REPAIR'], true)) {
                throw new InvalidArgumentException('A lost or damaged borrowing cannot be renewed.');
            }

            if ($borrowingStatus !== 'ISSUED' || $lockedBorrowing->effectiveStatus() === 'OVERDUE') {
                throw new InvalidArgumentException('Only current, non-overdue borrowings can be renewed.');
            }

            if (strtoupper((string) $copy->status) !== 'ISSUED') {
                throw new InvalidArgumentException('The physical copy is not currently issued.');
            }

            $renewalCount = max((int) $lockedBorrowing->renewal_count, $lockedBorrowing->renewals()->count());
            $maximumRenewals = min(
                self::MAX_RENEWALS,
                app(PaymentService::class)->integerSetting('maximum_renewals', self::MAX_RENEWALS)
            );
            if ($renewalCount >= $maximumRenewals) {
                throw new InvalidArgumentException('Maximum renewal limit reached.');
            }

            if (! $lockedBorrowing->member
                || ! $lockedBorrowing->member->user?->is_active
                || $lockedBorrowing->member->effectiveStatus() !== 'ACTIVE') {
                throw new InvalidArgumentException('Member membership is not active or has expired.');
            }

            if ((int) $lockedBorrowing->member->pradeshiya_sabha_id !== (int) $lockedBorrowing->pradeshiya_sabha_id) {
                throw new InvalidArgumentException('The borrowing and member must belong to the same Pradeshiya Sabha.');
            }

            if ($lockedBorrowing->member->fines()->where('status', 'UNPAID')->exists()) {
                throw new InvalidArgumentException('Outstanding fines must be resolved before renewing a book.');
            }

            $oldDueDate = Carbon::parse($lockedBorrowing->due_date);
            $issueDays = app(PaymentService::class)->integerSetting('issue_period_days', self::ISSUE_DAYS);
            $newDueDate = $oldDueDate->copy()->addDays($issueDays);
            $lockedBorrowing->update([
                'due_date' => $newDueDate->toDateString(),
                'renewal_count' => $renewalCount + 1,
                'status' => 'ISSUED',
            ]);
            $lockedBorrowing->renewals()->create([
                'old_due_date' => $oldDueDate->toDateString(),
                'new_due_date' => $newDueDate->toDateString(),
                'renewal_date' => Carbon::today()->toDateString(),
                'reason' => 'Staff-approved renewal',
            ]);

            return $lockedBorrowing->load(['member', 'book', 'bookCopy', 'renewals']);
        })
        );
    }

    public function activeBorrowingCount(Member $member): int
    {
        return $member->borrowings()->active()->count();
    }

    public function remainingSlots(Member $member): int
    {
        return max(0, $this->borrowingLimit($member) - $this->activeBorrowingCount($member));
    }

    public function borrowingLimit(Member $member): int
    {
        return app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            fn () => (int) ($member->membershipType?->max_books ?? 0)
        );
    }
}
