<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookInventory;
use App\Models\BookReservation;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReservationService
{
    public function reserve(Member $member, Book $book): BookReservation
    {
        if ($member->pradeshiya_sabha_id === null
            || $book->pradeshiya_sabha_id === null
            || (int) $member->pradeshiya_sabha_id !== (int) $book->pradeshiya_sabha_id) {
            throw new InvalidArgumentException('The member and book must belong to the same Pradeshiya Sabha.');
        }

        return app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            fn () => DB::transaction(function () use ($member, $book) {
                $member = Member::with('user')->lockForUpdate()->findOrFail($member->id);
                $book = Book::lockForUpdate()->findOrFail($book->id);

                if (! $member->user?->is_active || $member->effectiveStatus() !== 'ACTIVE') {
                    throw new InvalidArgumentException('Only active members can reserve books.');
                }

                if (! in_array(strtolower((string) $book->status), ['available', 'unavailable', 'reserved'], true)) {
                    throw new InvalidArgumentException('This book cannot be reserved.');
                }

                $inventory = BookInventory::query()
                    ->where('pradeshiya_sabha_id', $member->pradeshiya_sabha_id)
                    ->where('book_id', $book->id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory && $inventory->available_quantity > 0) {
                    throw new InvalidArgumentException('This book is currently available.');
                }

                if ($member->reservations()->where('book_id', $book->id)->where('status', 'PENDING')->exists()) {
                    throw new InvalidArgumentException('You already have an active reservation for this book.');
                }

                return BookReservation::create([
                    'pradeshiya_sabha_id' => $member->pradeshiya_sabha_id,
                    'member_id' => $member->id,
                    'book_id' => $book->id,
                    'status' => 'PENDING',
                    'reserved_at' => now(),
                ]);
            })
        );
    }

    public function cancel(Member $member, int $reservationId): BookReservation
    {
        return app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            fn () => DB::transaction(function () use ($member, $reservationId) {
                $reservation = $member->reservations()
                    ->whereKey($reservationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (strtoupper((string) $reservation->status) !== 'PENDING') {
                    throw new InvalidArgumentException('Only pending reservations can be cancelled.');
                }

                $reservation->update(['status' => 'CANCELLED']);

                return $reservation->refresh();
            })
        );
    }
}
