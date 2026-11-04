<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBorrowingRequest;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Member;
use App\Services\BorrowingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminBorrowingController extends Controller
{
    public function index(Request $request)
    {
        app(BorrowingService::class)->syncOverdue();
        $borrowings = Borrowing::with(['member', 'book', 'bookCopy', 'renewals'])
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('book_id'), fn ($query) => $query->where('book_id', $request->integer('book_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper($request->string('status')->toString())))
            ->latest('issue_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.borrowings.index', [
            'borrowings' => $borrowings,
            'members' => Member::orderBy('full_name')->get(),
            'books' => Book::orderBy('title')->get(),
        ]);
    }

    public function create(PaymentService $paymentService)
    {
        $issuePeriodDays = $paymentService->integerSetting('issue_period_days', BorrowingService::ISSUE_DAYS);

        return view('admin.issue-books', compact('issuePeriodDays'));
    }

    public function lookupMember(Request $request)
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string', 'max:64'],
        ]);
        $member = Member::with(['membershipType', 'user'])
            ->where('member_id', trim($validated['member_id']))
            ->firstOrFail();

        return response()->json([
            'id' => $member->id,
            'member_id' => $member->member_id,
            'name' => $member->full_name,
            'status' => $member->effectiveStatus(),
            'expiry_date' => $member->effectiveExpiryDate()?->toDateString(),
            'membership_type' => $member->membershipType?->name,
            'eligible' => $member->user?->is_active && $member->effectiveStatus() === 'ACTIVE',
        ]);
    }

    public function lookupBooks(Request $request)
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $search = trim($validated['search']);

        $books = Book::query()
            ->with(['author:id,name'])
            ->withCount(['copies as available_copies_count' => fn ($query) => $query->where('status', 'AVAILABLE')])
            ->withCount('copies')
            ->where(function ($query) use ($search) {
                $query->where('book_id', $search)
                    ->orWhere('isbn', $search)
                    ->orWhere('title', 'like', '%'.$search.'%');
            })
            ->orderBy('title')
            ->limit(10)
            ->get(['id', 'book_id', 'title', 'isbn', 'author_id', 'status']);

        return response()->json($books->map(fn (Book $book) => [
            'id' => $book->id,
            'book_id' => $book->book_id,
            'title' => $book->title,
            'isbn' => $book->isbn,
            'author' => $book->author?->name,
            'available_copies' => $book->available_copies_count,
            'total_copies' => $book->copies_count,
            'available' => $book->available_copies_count > 0,
        ]));
    }

    public function store(StoreBorrowingRequest $request, BorrowingService $service)
    {
        try {
            $borrowing = $service->issue(
                Member::findOrFail($request->integer('member_id')),
                Book::findOrFail($request->integer('book_id'))
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['issue' => $exception->getMessage()])->withInput();
        }

        return redirect()->route($this->routePrefix($request) . '.borrowings.index')
            ->with('success', 'Book issued successfully.');
    }

    public function returnBook(Request $request, Borrowing $borrowing, BorrowingService $service)
    {
        try {
            $service->returnBook($borrowing);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['borrowing' => $exception->getMessage()]);
        }

        return back()->with('success', 'Book returned successfully.');
    }

    public function renew(Request $request, Borrowing $borrowing, BorrowingService $service)
    {
        try {
            $service->renewBook($borrowing);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['borrowing' => $exception->getMessage()]);
        }

        return back()->with('success', 'Book renewed successfully.');
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('librarian.*') ? 'librarian' : 'admin';
    }
}
