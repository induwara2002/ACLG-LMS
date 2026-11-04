<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\DamageRecord;
use App\Models\LostBook;
use App\Models\Member;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchService
{
    public function globalSearch(?string $term, ?string $role = null): array
    {
        $term = trim((string) $term);

        $books = new Collection();
        $authors = new Collection();
        $publishers = new Collection();
        $categories = new Collection();
        $members = new Collection();
        $borrowings = new Collection();
        $lostBooks = new Collection();
        $damageRecords = new Collection();

        if ($term === '') {
            return compact('books', 'authors', 'publishers', 'categories', 'members', 'borrowings', 'lostBooks', 'damageRecords');
        }

        $books = Book::with(['author', 'publisher', 'category', 'copies'])
            ->where(function (Builder $query) use ($term) {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('book_id', 'like', "%{$term}%")
                    ->orWhere('isbn', 'like', "%{$term}%")
                    ->orWhere('edition', 'like', "%{$term}%")
                    ->orWhere('language', 'like', "%{$term}%")
                    ->orWhere('ddc_number', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereHas('author', fn (Builder $authorQuery) => $authorQuery->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('publisher', fn (Builder $publisherQuery) => $publisherQuery->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$term}%"));
            })
            ->limit(10)
            ->get();

        $authors = Author::where('name', 'like', "%{$term}%")->limit(10)->get();
        $publishers = Publisher::where('name', 'like', "%{$term}%")->limit(10)->get();
        $categories = Category::where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        })->limit(10)->get();

        if (in_array(strtoupper((string) $role), ['ADMIN', 'MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN'], true)) {
            $members = Member::where(function (Builder $query) use ($term) {
                $query->where('member_id', 'like', "%{$term}%")
                    ->orWhere('full_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('nic', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })->limit(10)->get();

            $borrowings = Borrowing::with(['member', 'book', 'bookCopy'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('id', 'like', "%{$term}%")
                        ->orWhereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_id', 'like', "%{$term}%"))
                        ->orWhereHas('book', fn (Builder $bookQuery) => $bookQuery->where('title', 'like', "%{$term}%")->orWhere('isbn', 'like', "%{$term}%"))
                        ->orWhereHas('bookCopy', fn (Builder $copyQuery) => $copyQuery->where('accession_number', 'like', "%{$term}%"));
                })->limit(10)->get();

            $lostBooks = LostBook::with(['member', 'book', 'bookCopy'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('id', 'like', "%{$term}%")
                        ->orWhere('payment_status', 'like', "%{$term}%")
                        ->orWhereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_id', 'like', "%{$term}%")->orWhere('full_name', 'like', "%{$term}%"))
                        ->orWhereHas('book', fn (Builder $bookQuery) => $bookQuery->where('title', 'like', "%{$term}%"))
                        ->orWhereHas('bookCopy', fn (Builder $copyQuery) => $copyQuery->where('accession_number', 'like', "%{$term}%"));
                })->limit(10)->get();

            $damageRecords = DamageRecord::with(['member', 'book', 'bookCopy'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('id', 'like', "%{$term}%")
                        ->orWhere('damage_type', 'like', "%{$term}%")
                        ->orWhere('payment_status', 'like', "%{$term}%")
                        ->orWhereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_id', 'like', "%{$term}%")->orWhere('full_name', 'like', "%{$term}%"))
                        ->orWhereHas('book', fn (Builder $bookQuery) => $bookQuery->where('title', 'like', "%{$term}%"))
                        ->orWhereHas('bookCopy', fn (Builder $copyQuery) => $copyQuery->where('accession_number', 'like', "%{$term}%"));
                })->limit(10)->get();
        }

        return compact('books', 'authors', 'publishers', 'categories', 'members', 'borrowings', 'lostBooks', 'damageRecords');
    }

    public function searchBooks(array $filters = [], bool $includeCopies = true): LengthAwarePaginator
    {
        $relations = ['author', 'publisher', 'category'];
        if ($includeCopies) {
            $relations[] = 'copies';
        } else {
            $relations[] = 'inventories';
        }
        $query = Book::query()->with($relations);

        $this->applyBookFilters($query, $filters, $includeCopies);

        return $query->orderBy($this->resolveSortField($filters['sort'] ?? 'title', ['title', 'isbn', 'publication_year', 'created_at']), $this->resolveSortDirection($filters['direction'] ?? 'asc'))->paginate(15)->withQueryString();
    }

    public function searchMembers(array $filters = []): LengthAwarePaginator
    {
        $query = Member::query()->with(['membershipType']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $memberQuery) use ($search) {
                $memberQuery->where('member_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nic', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['membership_type_id'])) {
            $query->where('membership_type_id', (int) $filters['membership_type_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['registration_from'])) {
            $query->whereDate('registration_date', '>=', $filters['registration_from']);
        }

        if (! empty($filters['registration_to'])) {
            $query->whereDate('registration_date', '<=', $filters['registration_to']);
        }

        if (! empty($filters['expiry_from'])) {
            $query->whereDate('expiry_date', '>=', $filters['expiry_from']);
        }

        if (! empty($filters['expiry_to'])) {
            $query->whereDate('expiry_date', '<=', $filters['expiry_to']);
        }

        $sortField = $this->resolveSortField($filters['sort'] ?? 'full_name', ['full_name', 'registration_date', 'expiry_date', 'created_at']);
        $sortDirection = $this->resolveSortDirection($filters['direction'] ?? 'asc');

        return $query->orderBy($sortField, $sortDirection)->paginate(15)->withQueryString();
    }

    public function searchBorrowings(array $filters = []): LengthAwarePaginator
    {
        $query = Borrowing::query()->with(['member', 'book', 'bookCopy', 'renewals']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $borrowingQuery) use ($search) {
                $borrowingQuery->where('id', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_id', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('book', fn (Builder $bookQuery) => $bookQuery->where('title', 'like', "%{$search}%")->orWhere('isbn', 'like', "%{$search}%"))
                    ->orWhereHas('bookCopy', fn (Builder $copyQuery) => $copyQuery->where('accession_number', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['member_id'])) {
            $query->where('member_id', (int) $filters['member_id']);
        }

        if (! empty($filters['book_id'])) {
            $query->where('book_id', (int) $filters['book_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        foreach (['issue_from' => 'issue_date', 'issue_to' => 'issue_date', 'due_from' => 'due_date', 'due_to' => 'due_date', 'return_from' => 'return_date', 'return_to' => 'return_date'] as $key => $column) {
            if (! empty($filters[$key])) {
                $comparison = str_contains($key, '_from') ? '>=' : '<=';
                $query->whereDate($column, $comparison, $filters[$key]);
            }
        }

        $sortField = $this->resolveSortField($filters['sort'] ?? 'issue_date', ['issue_date', 'due_date', 'return_date', 'created_at']);
        $sortDirection = $this->resolveSortDirection($filters['direction'] ?? 'desc');

        return $query->orderBy($sortField, $sortDirection)->paginate(15)->withQueryString();
    }

    public function searchLostBooks(array $filters = []): LengthAwarePaginator
    {
        $query = LostBook::query()->with(['member', 'book', 'bookCopy']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $lostQuery) use ($search) {
                $lostQuery->where('id', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_id', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('book', fn (Builder $bookQuery) => $bookQuery->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('bookCopy', fn (Builder $copyQuery) => $copyQuery->where('accession_number', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['member_id'])) {
            $query->where('member_id', (int) $filters['member_id']);
        }

        if (! empty($filters['borrowing_id'])) {
            $query->where('borrowing_id', (int) $filters['borrowing_id']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', strtoupper((string) $filters['payment_status']));
        }

        if (! empty($filters['lost_from'])) {
            $query->whereDate('lost_date', '>=', $filters['lost_from']);
        }

        if (! empty($filters['lost_to'])) {
            $query->whereDate('lost_date', '<=', $filters['lost_to']);
        }

        return $query->orderBy('lost_date', 'desc')->paginate(15)->withQueryString();
    }

    public function searchDamagedBooks(array $filters = []): LengthAwarePaginator
    {
        $query = DamageRecord::query()->with(['member', 'book', 'bookCopy']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $damageQuery) use ($search) {
                $damageQuery->where('id', 'like', "%{$search}%")
                    ->orWhere('damage_type', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_id', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('book', fn (Builder $bookQuery) => $bookQuery->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('bookCopy', fn (Builder $copyQuery) => $copyQuery->where('accession_number', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['member_id'])) {
            $query->where('member_id', (int) $filters['member_id']);
        }

        if (! empty($filters['borrowing_id'])) {
            $query->where('borrowing_id', (int) $filters['borrowing_id']);
        }

        if (! empty($filters['damage_type'])) {
            $query->where('damage_type', strtoupper((string) $filters['damage_type']));
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', strtoupper((string) $filters['payment_status']));
        }

        if (! empty($filters['damage_from'])) {
            $query->whereDate('damage_date', '>=', $filters['damage_from']);
        }

        if (! empty($filters['damage_to'])) {
            $query->whereDate('damage_date', '<=', $filters['damage_to']);
        }

        return $query->orderBy('damage_date', 'desc')->paginate(15)->withQueryString();
    }

    protected function applyBookFilters(Builder $query, array $filters, bool $includeCopies = true): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $bookQuery) use ($pattern, $includeCopies) {
                $bookQuery->whereRaw('LOWER(title) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(isbn) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(book_id) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(edition) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(language) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(ddc_number) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$pattern])
                    ->orWhereHas('author', fn (Builder $authorQuery) => $authorQuery->whereRaw('LOWER(name) LIKE ?', [$pattern]))
                    ->orWhereHas('publisher', fn (Builder $publisherQuery) => $publisherQuery->whereRaw('LOWER(name) LIKE ?', [$pattern]))
                    ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                        ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$pattern]));
                if ($includeCopies) {
                    $bookQuery->orWhereHas('copies', fn (Builder $copyQuery) => $copyQuery->whereRaw('LOWER(accession_number) LIKE ?', [$pattern]));
                }
            });
        }

        if (! empty($filters['author_id'])) {
            $query->where('author_id', (int) $filters['author_id']);
        }

        if (! empty($filters['publisher_id'])) {
            $query->where('publisher_id', (int) $filters['publisher_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['ddc'])) {
            $ddc = trim((string) $filters['ddc']);
            $query->where(function (Builder $ddcQuery) use ($ddc) {
                $ddcQuery->where('ddc_number', $ddc)
                    ->orWhere('ddc_number', 'like', $ddc.'.%')
                    ->orWhere('ddc_number', 'like', $ddc.'%');
            });
        }

        if (! empty($filters['language'])) {
            $query->where('language', trim((string) $filters['language']));
        }

        if (! empty($filters['edition'])) {
            $query->where('edition', 'like', '%'.trim((string) $filters['edition']).'%');
        }

        if (! empty($filters['publication_year'])) {
            $query->where('publication_year', (int) $filters['publication_year']);
        }

        if (! empty($filters['publication_year_from'])) {
            $query->where('publication_year', '>=', (int) $filters['publication_year_from']);
        }

        if (! empty($filters['publication_year_to'])) {
            $query->where('publication_year', '<=', (int) $filters['publication_year_to']);
        }

        if (! empty($filters['shelf_number'])) {
            $query->where('shelf_number', 'like', '%'.trim((string) $filters['shelf_number']).'%');
        }

        if (! empty($filters['availability'])) {
            $status = strtoupper((string) $filters['availability']);
            if ($includeCopies) {
                $query->whereHas('copies', fn (Builder $copyQuery) => $copyQuery->where('status', $status));
            } elseif ($status === 'AVAILABLE') {
                $query->whereHas('inventories', fn (Builder $inventoryQuery) => $inventoryQuery->where('available_quantity', '>', 0));
            } else {
                $query->whereDoesntHave('inventories', fn (Builder $inventoryQuery) => $inventoryQuery->where('available_quantity', '>', 0));
            }
        }
    }

    protected function resolveSortField(string $sort, array $allowed): string
    {
        return in_array($sort, $allowed, true) ? $sort : $allowed[0];
    }

    protected function resolveSortDirection(string $direction): string
    {
        return in_array(strtolower($direction), ['asc', 'desc'], true) ? strtolower($direction) : 'asc';
    }
}
