<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchBooksRequest;
use App\Http\Requests\SearchBorrowingsRequest;
use App\Http\Requests\SearchDamagedBooksRequest;
use App\Http\Requests\SearchLostBooksRequest;
use App\Http\Requests\SearchMembersRequest;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\Publisher;
use App\Models\PradeshiyaSabha;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(protected SearchService $searchService)
    {
    }

    public function globalSearch(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $user = $request->user();
        $role = $user?->isMainAdmin()
            ? User::ROLE_MAIN_ADMIN
            : $user?->role;

        $results = $this->searchService->globalSearch($term, $role);

        return view('search.index', [
            'query' => $term,
            'results' => $results,
            'canViewOperational' => $user?->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN') ?? false,
            'sabhas' => $this->sabhasFor($request),
        ]);
    }

    public function publicBooks(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = trim((string) ($filters['search'] ?? ''));
        $books = $this->searchService->searchBooks($filters, false);

        return view('search.public-books', [
            'books' => $books,
            'query' => $query,
        ]);
    }

    public function publicBook(Book $book)
    {
        $book->load(['author', 'publisher', 'category']);

        return view('search.public-book', compact('book'));
    }

    public function books(SearchBooksRequest $request)
    {
        $filters = $request->validated();
        $isMemberView = $request->user()?->hasRole('MEMBER') ?? false;
        $books = $this->searchService->searchBooks($filters, ! $isMemberView);
        $authors = Author::orderBy('name')->get();
        $publishers = Publisher::orderBy('name')->get();
        $categories = Category::orderBy('code')->get();
        $languages = Book::query()->select('language')->distinct()->orderBy('language')->pluck('language');

        return view('search.books', [
            'books' => $books,
            'authors' => $authors,
            'publishers' => $publishers,
            'categories' => $categories,
            'languages' => $languages,
            'filters' => $filters,
            'routePrefix' => $request->routeIs('member.*') ? 'member' : ($request->routeIs('librarian.*') ? 'librarian' : 'admin'),
            'isMemberView' => $isMemberView,
            'sabhas' => $this->sabhasFor($request),
        ]);
    }

    public function members(SearchMembersRequest $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN')) {
            abort(403, 'Unauthorized access.');
        }

        $members = $this->searchService->searchMembers($request->validated());
        $membershipTypes = MembershipType::orderBy('name')->get();

        return view('search.members', [
            'members' => $members,
            'membershipTypes' => $membershipTypes,
            'filters' => $request->validated(),
            'sabhas' => $this->sabhasFor($request),
        ]);
    }

    public function borrowings(SearchBorrowingsRequest $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN')) {
            abort(403, 'Unauthorized access.');
        }

        $borrowings = $this->searchService->searchBorrowings($request->validated());
        $members = Member::orderBy('full_name')->get();
        $books = Book::orderBy('title')->get();

        return view('search.borrowings', [
            'borrowings' => $borrowings,
            'members' => $members,
            'books' => $books,
            'filters' => $request->validated(),
            'sabhas' => $this->sabhasFor($request),
        ]);
    }

    public function lostBooks(SearchLostBooksRequest $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN')) {
            abort(403, 'Unauthorized access.');
        }

        $lostBooks = $this->searchService->searchLostBooks($request->validated());
        $members = Member::orderBy('full_name')->get();

        return view('search.lost-books', [
            'lostBooks' => $lostBooks,
            'members' => $members,
            'filters' => $request->validated(),
            'sabhas' => $this->sabhasFor($request),
        ]);
    }

    public function damagedBooks(SearchDamagedBooksRequest $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN')) {
            abort(403, 'Unauthorized access.');
        }

        $damageRecords = $this->searchService->searchDamagedBooks($request->validated());
        $members = Member::orderBy('full_name')->get();

        return view('search.damaged-books', [
            'damageRecords' => $damageRecords,
            'members' => $members,
            'filters' => $request->validated(),
            'sabhas' => $this->sabhasFor($request),
        ]);
    }

    private function sabhasFor(Request $request)
    {
        return $request->user()?->isMainAdmin()
            ? PradeshiyaSabha::query()->where('is_active', true)->orderBy('name')->get()
            : collect();
    }
}
