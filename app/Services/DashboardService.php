<?php

namespace App\Services;

use App\Exceptions\PradeshiyaSabhaConfigurationIncompleteException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookInventory;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\DamageRecord;
use App\Models\Fine;
use App\Models\LostBook;
use App\Models\Member;
use App\Models\MembershipPayment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private BorrowingService $borrowingService)
    {
    }

    public function admin(?int $year = null, bool $includeSabhaPresentation = false): array
    {
        $this->borrowingService->syncOverdue();
        $year ??= (int) now()->year;

        $data = [
            'bookStats' => $this->bookStats(),
            'memberStats' => $this->memberStats(),
            'borrowingStats' => $this->borrowingStats(),
            'financialStats' => $this->financialStats(),
            'operationalStats' => [
                'lostBooks' => LostBook::query()->count(),
                'damagedBooks' => DamageRecord::query()->count(),
            ],
            'monthlyStats' => $this->monthlyStats($year),
            'recent' => $this->recentActivity(),
            'recentMembers' => Member::query()->latest('registration_date')->limit(5)->get(),
            'alerts' => $this->alerts(),
            'year' => $year,
        ];

        if ($includeSabhaPresentation) {
            $data['categoryStats'] = $this->categoryStats();
            $data['dashboardTrends'] = $this->dashboardTrends();
        }

        return $data;
    }

    public function librarian(): array
    {
        $this->borrowingService->syncOverdue();

        return [
            'bookStats' => $this->bookStats(),
            'memberStats' => $this->memberStats(),
            'operationalStats' => [
                'overdue' => Borrowing::whereNull('return_date')->whereDate('due_date', '<', Carbon::today())->count(),
                'todayIssues' => Borrowing::whereDate('issue_date', Carbon::today())->count(),
                'todayReturns' => Borrowing::whereDate('return_date', Carbon::today())->count(),
                'booksDueToday' => Borrowing::query()
                    ->whereNull('return_date')
                    ->whereDate('due_date', Carbon::today())
                    ->count(),
                'activeReservations' => BookReservation::query()
                    ->where('status', 'PENDING')
                    ->count(),
                'lost' => LostBook::count(),
                'damaged' => DamageRecord::count(),
                'repair' => BookCopy::where('status', 'REPAIR')->count(),
            ],
            'recent' => $this->recentActivity(),
            'alerts' => $this->alerts(),
        ];
    }

    public function member(Member $member): array
    {
        $member->loadMissing(['membershipType', 'user', 'pradeshiyaSabha.settings']);
        $sabhaId = $member->pradeshiya_sabha_id;
        $membershipStatus = $member->portalStatus();

        $activeBorrowings = $member->borrowings()
            ->active()
            ->with(['book', 'bookCopy'])
            ->latest('issue_date')
            ->get();
        $today = Carbon::today();
        $overdueBorrowings = $activeBorrowings
            ->filter(fn (Borrowing $borrowing) => $borrowing->due_date && $borrowing->due_date->lt($today))
            ->values();
        $dueSoonBorrowings = $activeBorrowings
            ->filter(fn (Borrowing $borrowing) => $borrowing->due_date
                && $borrowing->due_date->gte($today)
                && $borrowing->due_date->lte($today->copy()->addDays(7)))
            ->values();
        try {
            $maximumRenewals = app(PradeshiyaSabhaContext::class)->runForId(
                $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
                fn () => app(PaymentService::class)->integerSetting('maximum_renewals', BorrowingService::MAX_RENEWALS)
            );
        } catch (PradeshiyaSabhaConfigurationIncompleteException) {
            $maximumRenewals = BorrowingService::MAX_RENEWALS;
        }
        $outstandingFines = $member->fines()->where('status', 'UNPAID')->latest('issued_date')->get();
        $availableRenewalCount = $member->user?->is_active
            && $membershipStatus === 'ACTIVE'
            && $outstandingFines->isEmpty()
            ? $activeBorrowings->filter(fn (Borrowing $borrowing) => $borrowing->effectiveStatus() !== 'OVERDUE'
                && strtoupper((string) $borrowing->status) === 'ISSUED'
                && $borrowing->renewal_count < $maximumRenewals
                && ! in_array(strtoupper((string) $borrowing->status), ['LOST', 'DAMAGED'], true)
                && strtoupper((string) $borrowing->bookCopy?->status) === 'ISSUED')->count()
            : 0;
        $recentPayments = $member->membershipPayments()->latest('payment_date')->limit(5)->get()
            ->concat($member->payments()->latest('payment_date')->limit(5)->get())
            ->sortByDesc('payment_date')
            ->take(5)
            ->values();
        $borrowingHistory = $member->borrowings()
            ->with('book')
            ->latest('issue_date')
            ->limit(5)
            ->get();
        $activeReservationCount = $sabhaId === null
            ? 0
            : $member->reservations()->where('pradeshiya_sabha_id', $sabhaId)->where('status', 'PENDING')->count();
        $reservations = $sabhaId === null
            ? collect()
            : $member->reservations()
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->with('book.category')
                ->latest('reserved_at')
                ->limit(5)
                ->get();
        $availableInventories = $sabhaId === null
            ? collect()
            : BookInventory::query()
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->where('available_quantity', '>', 0)
                ->whereHas('book', fn ($query) => $query->where('status', 'available'))
                ->with('book.author', 'book.category')
                ->latest('updated_at')
                ->limit(4)
                ->get();
        $categories = $sabhaId === null
            ? collect()
            : Category::query()->orderBy('name')->get();
        $availableBookCount = $sabhaId === null
            ? 0
            : BookInventory::query()
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->where('available_quantity', '>', 0)
                ->count();

        return [
            'activeBorrowings' => $activeBorrowings,
            'borrowingHistory' => $borrowingHistory,
            'dueSoonBorrowings' => $dueSoonBorrowings,
            'overdueBorrowings' => $overdueBorrowings,
            'activeBorrowingCount' => $activeBorrowings->count(),
            'availableBookCount' => $availableBookCount,
            'availableInventories' => $availableInventories,
            'categories' => $categories,
            'reservations' => $reservations,
            'activeReservationCount' => $activeReservationCount,
            'membershipStatus' => $membershipStatus,
            'overdueCount' => $overdueBorrowings->count(),
            'borrowingLimit' => $this->borrowingService->borrowingLimit($member),
            'remainingAllowance' => $this->borrowingService->remainingSlots($member),
            'maximumRenewals' => $maximumRenewals,
            'availableRenewalCount' => $availableRenewalCount,
            'membershipExpiringSoon' => $member->expiry_date !== null
                && $member->expiry_date->gte($today)
                && $member->expiry_date->lte($today->copy()->addDays(30)),
            'payments' => $recentPayments,
            'outstandingFines' => $outstandingFines,
            'outstandingCompensation' => $member->payments()
                ->whereIn('payment_type', ['DAMAGE_COMPENSATION', 'LOST_BOOK_COMPENSATION'])
                ->where('status', 'UNPAID')
                ->latest('payment_date')
                ->get(),
        ];
    }

    private function bookStats(): array
    {
        $statuses = ['AVAILABLE', 'ISSUED', 'OVERDUE', 'LOST', 'DAMAGED', 'REPAIR'];
        $copyCounts = BookCopy::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->whereIn('status', $statuses)
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'titles' => Book::count(),
            'copies' => BookCopy::count(),
            'available' => (int) ($copyCounts['AVAILABLE'] ?? 0),
            'issued' => (int) ($copyCounts['ISSUED'] ?? 0),
            'overdue' => (int) ($copyCounts['OVERDUE'] ?? 0),
            'lost' => (int) ($copyCounts['LOST'] ?? 0),
            'damaged' => (int) ($copyCounts['DAMAGED'] ?? 0),
            'repair' => (int) ($copyCounts['REPAIR'] ?? 0),
        ];
    }

    private function memberStats(): array
    {
        $members = Member::query()
            ->with('membershipType:id,name')
            ->select('id', 'status', 'membership_type_id', 'expiry_date', 'membership_start_date', 'registration_date')
            ->get();
        $effectiveStatuses = $members->map(fn (Member $member) => $member->effectiveStatus());

        return [
            'total' => $members->count(),
            'active' => $effectiveStatuses->filter(fn ($status) => $status === 'ACTIVE')->count(),
            'expired' => $effectiveStatuses->filter(fn ($status) => $status === 'EXPIRED')->count(),
            'suspended' => $effectiveStatuses->filter(fn ($status) => $status === 'SUSPENDED')->count(),
            'children' => $members->filter(fn (Member $member) => strtoupper((string) $member->membershipType?->name) === 'CHILDREN')->count(),
            'elders' => $members->filter(fn (Member $member) => strtoupper((string) $member->membershipType?->name) === 'ELDERS')->count(),
        ];
    }

    private function categoryStats(): array
    {
        $categories = Category::query()
            ->whereHas('books', fn ($query) => $query->whereHas('copies'))
            ->withCount(['books' => fn ($query) => $query->whereHas('copies')])
            ->orderByDesc('books_count')
            ->orderBy('name')
            ->get();
        $uncategorizedBooks = Book::query()->whereNull('category_id')->whereHas('copies')->count();
        $visibleCategories = $categories->take(5);
        $otherBooks = $categories->skip(5)->sum('books_count') + $uncategorizedBooks;
        $labels = $visibleCategories->pluck('name')->values();
        $data = $visibleCategories->pluck('books_count')->map(fn ($count) => (int) $count)->values();

        if ($otherBooks > 0) {
            $labels->push('Others');
            $data->push((int) $otherBooks);
        }

        return [
            'labels' => $labels->all(),
            'data' => $data->all(),
            'total' => (int) $categories->sum('books_count') + $uncategorizedBooks,
        ];
    }

    private function dashboardTrends(): array
    {
        $today = Carbon::today();
        $thisMonthStart = $today->copy()->startOfMonth();
        $previousMonthStart = $thisMonthStart->copy()->subMonth();
        $previousMonthEnd = $thisMonthStart->copy()->subDay();

        $previousMembers = Member::query()
            ->whereDate('registration_date', '<=', $previousMonthEnd)
            ->count();
        $previousCopies = BookCopy::query()
            ->whereDate('acquisition_date', '<=', $previousMonthEnd)
            ->count();
        $previousBorrowings = Borrowing::query()
            ->whereIn('status', ['ISSUED', 'OVERDUE', 'issued', 'overdue'])
            ->whereDate('issue_date', '<=', $previousMonthEnd)
            ->where(function ($query) use ($previousMonthEnd) {
                $query->whereNull('return_date')
                    ->orWhereDate('return_date', '>', $previousMonthEnd);
            });
        $previousBorrowed = (clone $previousBorrowings)->count();
        $previousOverdue = (clone $previousBorrowings)
            ->whereDate('due_date', '<', $previousMonthEnd)
            ->count();

        $currentMonthIssues = Borrowing::query()
            ->whereBetween('issue_date', [$thisMonthStart, $today])
            ->count();
        $previousMonthIssues = Borrowing::query()
            ->whereBetween('issue_date', [$previousMonthStart, $previousMonthEnd])
            ->count();

        return [
            'members' => $this->comparison(Member::query()->count(), $previousMembers),
            'books' => $this->comparison(BookCopy::query()->count(), $previousCopies),
            'issued' => $this->comparison($currentMonthIssues, $previousMonthIssues),
            'borrowed' => $this->comparison(
                Borrowing::query()->whereNull('return_date')->whereIn('status', ['ISSUED', 'OVERDUE', 'issued', 'overdue'])->count(),
                $previousBorrowed
            ),
            'overdue' => $this->comparison(
                Borrowing::query()->whereNull('return_date')->whereDate('due_date', '<', $today)->count(),
                $previousOverdue
            ),
        ];
    }

    private function comparison(int $current, int $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'percent' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
            'direction' => $current <=> $previous,
        ];
    }

    private function borrowingStats(): array
    {
        $renewals = DB::table('renewals');
        if (($sabhaId = app(PradeshiyaSabhaContext::class)->id()) !== null) {
            $renewals->where('pradeshiya_sabha_id', $sabhaId);
        }

        return [
            'issued' => Borrowing::whereNull('return_date')->whereIn('status', ['ISSUED', 'OVERDUE'])->count(),
            'overdue' => Borrowing::whereNull('return_date')->whereDate('due_date', '<', Carbon::today())->count(),
            'returned' => Borrowing::whereNotNull('return_date')->count(),
            'renewals' => $renewals->count(),
            'todayIssues' => Borrowing::whereDate('issue_date', Carbon::today())->count(),
            'todayReturns' => Borrowing::whereDate('return_date', Carbon::today())->count(),
        ];
    }

    private function financialStats(): array
    {
        $paymentTotals = Payment::query()
            ->where('status', 'PAID')
            ->select('payment_type', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_type')
            ->pluck('total', 'payment_type');

        $membershipTotals = MembershipPayment::query()
            ->where('status', 'PAID')
            ->select('payment_type', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_type')
            ->pluck('total', 'payment_type');

        $membershipPayments = PaymentService::sumAmounts($membershipTotals->values()->concat($paymentTotals->only(['REGISTRATION', 'MONTHLY_MEMBERSHIP', 'LATE_RENEWAL'])->values()));
        $outstandingCompensation = PaymentService::sumAmounts([
            LostBook::where('payment_status', 'UNPAID')->sum('compensation_amount'),
            DamageRecord::where('payment_status', 'UNPAID')->sum('compensation_amount'),
        ]);

        return [
            'paymentCount' => Payment::query()->count() + MembershipPayment::query()->count(),
            'membershipPayments' => $membershipPayments,
            'registration' => (string) ($membershipTotals['REGISTRATION'] ?? 0),
            'monthlyMembership' => (string) ($membershipTotals['MONTHLY_MEMBERSHIP'] ?? 0),
            'lateRenewal' => (string) ($membershipTotals['LATE_RENEWAL'] ?? 0),
            'overdueFines' => (string) ($paymentTotals['OVERDUE_FINE'] ?? 0),
            'damageCompensation' => (string) ($paymentTotals['DAMAGE_COMPENSATION'] ?? 0),
            'lostCompensation' => (string) ($paymentTotals['LOST_BOOK_COMPENSATION'] ?? 0),
            'unpaidFines' => (string) Fine::where('status', 'UNPAID')->sum('amount'),
            'outstandingCompensation' => $outstandingCompensation,
        ];
    }

    private function monthlyStats(int $year): array
    {
        $months = collect(range(1, 12));
        $sabhaId = app(PradeshiyaSabhaContext::class)->id();
        $group = function (string $table, string $date) use ($year, $sabhaId) {
            $query = DB::table($table)
                ->select(DB::raw('MONTH('.$date.') as month'), DB::raw('COUNT(*) as total'))
                ->whereYear($date, $year)
                ->groupBy(DB::raw('MONTH('.$date.')'));
            if ($sabhaId !== null) {
                $query->where('pradeshiya_sabha_id', $sabhaId);
            }

            return $query->pluck('total', 'month');
        };

        $memberTotals = $group('members', 'registration_date');
        $issueTotals = $group('borrowings', 'issue_date');
        $returnTotals = $group('borrowings', 'return_date');
        $lostTotals = $group('lost_books', 'lost_date');
        $damageTotals = $group('damage_records', 'damage_date');
        $fineQuery = DB::table('fines')
            ->select(DB::raw('MONTH(paid_date) as month'), DB::raw('SUM(amount) as total'))
            ->whereYear('paid_date', $year)
            ->where('status', 'PAID')
            ->groupBy(DB::raw('MONTH(paid_date)'));
        if ($sabhaId !== null) {
            $fineQuery->where('pradeshiya_sabha_id', $sabhaId);
        }
        $fineTotals = $fineQuery->pluck('total', 'month');

        $paymentQuery = DB::table('payments')
            ->select(DB::raw('MONTH(payment_date) as month'), DB::raw('SUM(amount) as total'))
            ->whereYear('payment_date', $year)
            ->where('status', 'PAID')
            ->groupBy(DB::raw('MONTH(payment_date)'));
        if ($sabhaId !== null) {
            $paymentQuery->where('pradeshiya_sabha_id', $sabhaId);
        }
        $paymentGroup = $paymentQuery->pluck('total', 'month');

        return [
            'labels' => $months->map(fn ($month) => Carbon::create()->month($month)->format('M'))->all(),
            'members' => $months->map(fn ($month) => (int) ($memberTotals->get($month) ?? 0))->all(),
            'issues' => $months->map(fn ($month) => (int) ($issueTotals->get($month) ?? 0))->all(),
            'returns' => $months->map(fn ($month) => (int) ($returnTotals->get($month) ?? 0))->all(),
            'payments' => $months->map(fn ($month) => (string) ($paymentGroup->get($month) ?? 0))->all(),
            'fines' => $months->map(fn ($month) => (string) ($fineTotals->get($month) ?? 0))->all(),
            'lost' => $months->map(fn ($month) => (int) ($lostTotals->get($month) ?? 0))->all(),
            'damaged' => $months->map(fn ($month) => (int) ($damageTotals->get($month) ?? 0))->all(),
        ];
    }

    private function recentActivity(): array
    {
        return [
            'borrowings' => Borrowing::with(['member', 'book'])->latest('created_at')->limit(5)->get(),
            'payments' => Payment::with('member')->latest('created_at')->limit(5)->get(),
            'members' => Member::latest('created_at')->limit(5)->get(),
            'lostBooks' => LostBook::with(['member', 'book'])->latest('created_at')->limit(5)->get(),
            'damageRecords' => DamageRecord::with(['member', 'book'])->latest('created_at')->limit(5)->get(),
        ];
    }

    private function alerts(): array
    {
        $today = Carbon::today();
        $expiryWindowEnd = $today->copy()->addDays(30);
        $membershipCandidates = Member::query()
            ->where('status', '!=', 'SUSPENDED')
            ->where(function ($query) use ($expiryWindowEnd) {
                $query->whereDate('expiry_date', '<=', $expiryWindowEnd)
                    ->orWhereNull('expiry_date');
            })
            ->get(['id', 'status', 'expiry_date', 'membership_start_date', 'registration_date']);
        $expiredMembers = $membershipCandidates
            ->filter(fn (Member $member) => $member->effectiveStatus() === 'EXPIRED')
            ->count();
        $membershipRenewals = $membershipCandidates
            ->filter(fn (Member $member) => $member->effectiveExpiryDate()?->lte($expiryWindowEnd) ?? false)
            ->sortBy(fn (Member $member) => $member->effectiveExpiryDate()?->getTimestamp() ?? PHP_INT_MAX)
            ->take(10)
            ->values();

        return [
            'overdue' => Borrowing::whereNull('return_date')->whereDate('due_date', '<', Carbon::today())->count(),
            'overdueRecords' => Borrowing::query()
                ->with(['member', 'book'])
                ->whereNull('return_date')
                ->whereDate('due_date', '<', $today)
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
            'expiredMembers' => $expiredMembers,
            'membershipRenewals' => $membershipRenewals,
            'unpaidFines' => Fine::where('status', 'UNPAID')->count(),
            'unpaidFineRecords' => Fine::query()
                ->with('member')
                ->where('status', 'UNPAID')
                ->latest('issued_date')
                ->limit(10)
                ->get(),
            'unpaidCompensation' => LostBook::where('payment_status', 'UNPAID')->count() + DamageRecord::where('payment_status', 'UNPAID')->count(),
            'lostBooks' => LostBook::query()->count(),
            'lostBookRecords' => LostBook::query()
                ->with(['member', 'book'])
                ->latest('lost_date')
                ->limit(10)
                ->get(),
            'damagedBooks' => DamageRecord::query()->count(),
            'damageRecords' => DamageRecord::query()
                ->with(['member', 'book'])
                ->latest('damage_date')
                ->limit(10)
                ->get(),
            'repair' => BookCopy::where('status', 'REPAIR')->count(),
        ];
    }
}
