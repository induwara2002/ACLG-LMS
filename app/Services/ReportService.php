<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\DamageRecord;
use App\Models\Fine;
use App\Models\LostBook;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function books(array $filters = []): LengthAwarePaginator
    {
        $query = Book::query()
            ->with(['author', 'publisher', 'category'])
            ->withCount('copies')
            ->withCount(['copies as available_copies' => fn ($copyQuery) => $copyQuery->where('status', 'AVAILABLE')]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $bookQuery) use ($search) {
                $bookQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%")
                    ->orWhere('book_id', 'like', "%{$search}%")
                    ->orWhereHas('author', fn (Builder $authorQuery) => $authorQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('publisher', fn (Builder $publisherQuery) => $publisherQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
            });
        }

        foreach (['author_id', 'publisher_id', 'category_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        foreach (['ddc_number' => 'ddc', 'language' => 'language', 'edition' => 'edition'] as $column => $filter) {
            if (! empty($filters[$filter])) {
                $query->where($column, 'like', '%'.trim((string) $filters[$filter]).'%');
            }
        }

        if (! empty($filters['publication_year'])) {
            $query->where('publication_year', (int) $filters['publication_year']);
        }

        if (! empty($filters['availability'])) {
            $query->whereHas('copies', fn (Builder $copyQuery) => $copyQuery->where('status', strtoupper((string) $filters['availability'])));
        }

        return $query->orderBy('title')->paginate(20)->withQueryString();
    }

    public function members(array $filters = []): LengthAwarePaginator
    {
        $query = Member::query()->with('membershipType');
        $this->applyMemberFilters($query, $filters);

        return $query->orderBy('full_name')->paginate(20)->withQueryString();
    }

    public function borrowings(array $filters = []): LengthAwarePaginator
    {
        $query = Borrowing::query()->with(['member', 'book', 'bookCopy', 'renewals']);
        $this->applyBorrowingFilters($query, $filters);

        return $query->orderByDesc('issue_date')->paginate(20)->withQueryString();
    }

    public function overdue(array $filters = []): LengthAwarePaginator
    {
        $query = Borrowing::query()->with(['member', 'book', 'bookCopy', 'fines'])
            ->whereNull('return_date')
            ->whereDate('due_date', '<', today());
        $this->applyBorrowingFilters($query, $filters, false);

        return $query->orderBy('due_date')->paginate(20)->withQueryString();
    }

    public function returns(array $filters = []): LengthAwarePaginator
    {
        $query = Borrowing::query()->with(['member', 'book', 'bookCopy', 'renewals'])
            ->whereNotNull('return_date');
        $this->applyBorrowingFilters($query, $filters, false, 'return_date');

        return $query->orderByDesc('return_date')->paginate(20)->withQueryString();
    }

    public function payments(array $filters = []): LengthAwarePaginator
    {
        $sabhaId = app(PradeshiyaSabhaContext::class)->id();
        $paymentQuery = DB::table('payments')
            ->select('payments.id', 'payments.member_id', 'payments.payment_type', 'payments.amount', 'payments.payment_date', 'payments.status', 'payments.reference_number as reference', 'payments.description')
            ->selectRaw("'PAYMENT' as source");
        $membershipQuery = DB::table('membership_payments')
            ->select('membership_payments.id', 'membership_payments.member_id', 'membership_payments.payment_type', 'membership_payments.amount', 'membership_payments.payment_date', 'membership_payments.status', 'membership_payments.reference', 'membership_payments.description')
            ->selectRaw("'MEMBERSHIP_PAYMENT' as source");

        if ($sabhaId !== null) {
            $paymentQuery->where('payments.pradeshiya_sabha_id', $sabhaId);
            $membershipQuery->where('membership_payments.pradeshiya_sabha_id', $sabhaId);
        }

        foreach ([$paymentQuery, $membershipQuery] as $query) {
            if (! empty($filters['member_id'])) {
                $query->where('member_id', (int) $filters['member_id']);
            }
            if (! empty($filters['payment_type'])) {
                $query->where('payment_type', strtoupper((string) $filters['payment_type']));
            }
            if (! empty($filters['status'])) {
                $query->where('status', strtoupper((string) $filters['status']));
            }
            $this->applyDateRange($query, 'payment_date', $filters);
        }

        $union = $paymentQuery->unionAll($membershipQuery);
        return DB::query()->fromSub($union, 'report_payments')->orderByDesc('payment_date')->paginate(20)->withQueryString();
    }

    public function fines(array $filters = []): LengthAwarePaginator
    {
        $query = Fine::query()->with(['member', 'borrowing']);
        if (! empty($filters['member_id'])) {
            $query->where('member_id', (int) $filters['member_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['fine_type'])) {
            $query->where('fine_type', strtoupper((string) $filters['fine_type']));
        }
        $this->applyDateRange($query, 'issued_date', $filters);

        return $query->orderByDesc('issued_date')->paginate(20)->withQueryString();
    }

    public function lostBooks(array $filters = []): LengthAwarePaginator
    {
        $query = LostBook::query()->with(['member', 'borrowing', 'book', 'bookCopy']);
        $this->applyIncidentFilters($query, $filters, 'lost_date');

        return $query->orderByDesc('lost_date')->paginate(20)->withQueryString();
    }

    public function damagedBooks(array $filters = []): LengthAwarePaginator
    {
        $query = DamageRecord::query()->with(['member', 'borrowing', 'book', 'bookCopy']);
        $this->applyIncidentFilters($query, $filters, 'damage_date');
        if (! empty($filters['damage_type'])) {
            $query->where('damage_type', strtoupper((string) $filters['damage_type']));
        }

        return $query->orderByDesc('damage_date')->paginate(20)->withQueryString();
    }

    public function compensation(array $filters = []): LengthAwarePaginator
    {
        $sabhaId = app(PradeshiyaSabhaContext::class)->id();
        $query = DB::table('payments')
            ->leftJoin('members', 'members.id', '=', 'payments.member_id')
            ->leftJoin('borrowings', 'borrowings.id', '=', 'payments.borrowing_id')
            ->leftJoin('books', 'books.id', '=', 'borrowings.book_id')
            ->leftJoin('book_copies', 'book_copies.id', '=', 'borrowings.book_copy_id')
            ->whereIn('payments.payment_type', ['DAMAGE_COMPENSATION', 'LOST_BOOK_COMPENSATION'])
            ->select('payments.id', 'members.full_name as member_name', 'books.title', 'book_copies.accession_number', 'payments.payment_type', 'payments.amount', 'payments.status', 'payments.payment_date', 'payments.reference_number');

        if ($sabhaId !== null) {
            $query->where('payments.pradeshiya_sabha_id', $sabhaId);
        }

        if (! empty($filters['member_id'])) {
            $query->where('payments.member_id', (int) $filters['member_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('payments.status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['payment_type'])) {
            $query->where('payments.payment_type', strtoupper((string) $filters['payment_type']));
        }
        $this->applyDateRange($query, 'payments.payment_date', $filters);

        return $query->orderByDesc('payments.payment_date')->paginate(20)->withQueryString();
    }

    private function applyMemberFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $memberQuery) use ($search) {
                $memberQuery->where('member_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nic', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['membership_type_id'])) {
            $query->where('membership_type_id', (int) $filters['membership_type_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        $this->applyDateRange($query, 'registration_date', $filters, 'registration_from', 'registration_to');
        $this->applyDateRange($query, 'expiry_date', $filters, 'expiry_from', 'expiry_to');
    }

    private function applyBorrowingFilters(Builder $query, array $filters, bool $includeStatus = true, string $dateColumn = 'issue_date'): void
    {
        if (! empty($filters['member_id'])) {
            $query->where('member_id', (int) $filters['member_id']);
        }
        if (! empty($filters['book_id'])) {
            $query->where('book_id', (int) $filters['book_id']);
        }
        if ($includeStatus && ! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        $this->applyDateRange($query, $dateColumn, $filters);
    }

    private function applyIncidentFilters(Builder $query, array $filters, string $dateColumn): void
    {
        if (! empty($filters['member_id'])) {
            $query->where('member_id', (int) $filters['member_id']);
        }
        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', strtoupper((string) $filters['payment_status']));
        }
        $this->applyDateRange($query, $dateColumn, $filters);
    }

    private function applyDateRange($query, string $column, array $filters, string $fromKey = 'date_from', string $toKey = 'date_to'): void
    {
        if (! empty($filters[$fromKey])) {
            $query->whereDate($column, '>=', $filters[$fromKey]);
        }
        if (! empty($filters[$toKey])) {
            $query->whereDate($column, '<=', $filters[$toKey]);
        }
    }
}
