<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrowing;
use App\Services\BorrowingService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MemberPortalController extends Controller
{
    public function book(Request $request, Book $book)
    {
        $book->load(['author', 'publisher', 'category', 'inventories']);
        $member = $request->user()->member()->firstOrFail();
        $activeReservation = $member->reservations()
            ->where('book_id', $book->id)
            ->where('status', 'PENDING')
            ->first();
        $canReserve = $member->user?->is_active
            && $member->portalStatus() === 'ACTIVE'
            && $book->inventories->sum('available_quantity') === 0;

        return view('member.book', compact('book', 'activeReservation', 'canReserve'));
    }

    public function reserve(Request $request, Book $book, ReservationService $service)
    {
        $member = $request->user()->member()->firstOrFail();

        try {
            $service->reserve($member, $book);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('Book reserved successfully.'));
    }

    public function cancelReservation(Request $request, int $reservation, ReservationService $service)
    {
        $member = $request->user()->member()->firstOrFail();

        try {
            $service->cancel($member, $reservation);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('Reservation cancelled successfully.'));
    }

    public function alerts(Request $request)
    {
        $member = $request->user()->member()->with('membershipType')->firstOrFail();
        $today = Carbon::today();
        $activeBorrowings = $member->borrowings()
            ->whereNull('return_date')
            ->with('book')
            ->orderBy('due_date')
            ->get();
        $overdueBorrowings = $activeBorrowings
            ->filter(fn (Borrowing $borrowing) => $borrowing->due_date && $borrowing->due_date->lt($today))
            ->values();
        $dueSoonBorrowings = $activeBorrowings
            ->filter(fn (Borrowing $borrowing) => $borrowing->due_date
                && $borrowing->due_date->gte($today)
                && $borrowing->due_date->lte($today->copy()->addDays(7)))
            ->values();
        $outstandingFines = $member->fines()
            ->where('status', 'UNPAID')
            ->latest('issued_date')
            ->get();
        $outstandingCompensation = $member->payments()
            ->whereIn('payment_type', ['DAMAGE_COMPENSATION', 'LOST_BOOK_COMPENSATION'])
            ->where('status', 'UNPAID')
            ->latest('payment_date')
            ->get();
        $membershipExpiringSoon = $member->expiry_date !== null
            && $member->expiry_date->gte($today)
            && $member->expiry_date->lte($today->copy()->addDays(30));

        return view('member.alerts', compact(
            'member',
            'overdueBorrowings',
            'dueSoonBorrowings',
            'outstandingFines',
            'outstandingCompensation',
            'membershipExpiringSoon'
        ));
    }

    public function renew(Request $request, int $borrowing, BorrowingService $service)
    {
        $member = $request->user()->member()->firstOrFail();
        abort_unless($member->portalStatus() === 'ACTIVE', 403, 'Only active members can renew books.');
        $ownedBorrowing = $member->borrowings()->findOrFail($borrowing);

        if ($ownedBorrowing->effectiveStatus() === 'OVERDUE') {
            return back()->withErrors(['borrowing' => 'Overdue borrowings cannot be renewed through the member portal.']);
        }

        if ($member->fines()->where('status', 'UNPAID')->exists()) {
            return back()->withErrors(['borrowing' => 'Outstanding fines must be resolved before renewing a book.']);
        }

        try {
            $service->renewBook($ownedBorrowing);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['borrowing' => $exception->getMessage()]);
        }

        return redirect()->route('member.borrowings')->with('success', 'Book renewed successfully.');
    }
}