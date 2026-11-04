<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Services\BorrowingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class AdminRenewalController extends Controller
{
    public function index(Request $request, PaymentService $paymentService)
    {
        $maximumRenewals = min(
            BorrowingService::MAX_RENEWALS,
            $paymentService->integerSetting('maximum_renewals', BorrowingService::MAX_RENEWALS)
        );
        $issuePeriodDays = $paymentService->integerSetting('issue_period_days', BorrowingService::ISSUE_DAYS);
        $borrowings = Borrowing::query()
            ->with([
                'member.membershipType',
                'member.user',
                'member.fines' => fn ($query) => $query->where('status', 'UNPAID'),
                'book',
                'bookCopy',
                'renewals',
            ])
            ->latest('issue_date')
            ->paginate(15)
            ->withQueryString();
        $routePrefix = $request->routeIs('librarian.*') ? 'librarian' : 'admin';

        return view('admin.renewals.index', compact('borrowings', 'maximumRenewals', 'issuePeriodDays', 'routePrefix'));
    }
}
