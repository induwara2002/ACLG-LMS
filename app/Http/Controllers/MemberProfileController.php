<?php

namespace App\Http\Controllers;

use App\Exceptions\PradeshiyaSabhaConfigurationIncompleteException;
use App\Models\Borrowing;
use App\Services\BorrowingService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;

class MemberProfileController extends Controller
{
    public function show()
    {
        $member = Auth::user()->member()->with(['membershipType', 'membershipPayments' => fn ($query) => $query->latest('payment_date')])->firstOrFail();

        return view('member.profile', compact('member'));
    }

    public function payments()
    {
        $member = Auth::user()->member()->with([
            'membershipPayments' => fn ($query) => $query->latest('payment_date'),
            'payments' => fn ($query) => $query->latest('payment_date'),
            'fines',
        ])->firstOrFail();

        return view('member.payments', compact('member'));
    }

    public function fines()
    {
        $member = Auth::user()->member()->with([
            'fines' => fn ($query) => $query->latest('issued_date'),
        ])->firstOrFail();

        return view('member.fines', compact('member'));
    }

    public function outstandingBalance()
    {
        $member = Auth::user()->member()->with('fines')->firstOrFail();

        return view('member.outstanding-balance', compact('member'));
    }

    public function lostBooks()
    {
        $member = Auth::user()->member()->with([
            'lostBooks' => fn ($query) => $query->latest('lost_date'),
            'lostBooks.book',
            'lostBooks.bookCopy',
        ])->firstOrFail();

        return view('member.lost-books', compact('member'));
    }

    public function damagedBooks()
    {
        $member = Auth::user()->member()->with([
            'damageRecords' => fn ($query) => $query->latest('damage_date'),
            'damageRecords.book',
            'damageRecords.bookCopy',
        ])->firstOrFail();

        return view('member.damaged-books', compact('member'));
    }

    public function borrowings(BorrowingService $borrowingService, PaymentService $paymentService)
    {
        $borrowingService->syncOverdue();
        $member = auth()->user()->member()->with('membershipType')->firstOrFail();
        $borrowings = Borrowing::with(['book', 'bookCopy', 'renewals'])
            ->where('member_id', $member->id)
            ->latest('issue_date')
            ->paginate(15);
        $hasOutstandingFines = $member->fines()->where('status', 'UNPAID')->exists();
        try {
            $maximumRenewals = $paymentService->integerSetting('maximum_renewals', BorrowingService::MAX_RENEWALS);
        } catch (PradeshiyaSabhaConfigurationIncompleteException) {
            $maximumRenewals = BorrowingService::MAX_RENEWALS;
        }

        return view('member.borrowings', compact('member', 'borrowings', 'maximumRenewals', 'hasOutstandingFines'));
    }
}