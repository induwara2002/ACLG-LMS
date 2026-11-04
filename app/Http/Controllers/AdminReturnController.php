<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Services\BorrowingService;
use Illuminate\Http\Request;

class AdminReturnController extends Controller
{
    public function index(Request $request, BorrowingService $service)
    {
        $service->syncOverdue();
        $borrowings = Borrowing::query()
            ->with(['member.membershipType', 'member.fines', 'book', 'bookCopy'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = mb_strtolower(trim($request->string('search')->toString()));
                $query->where(function ($query) use ($search) {
                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                    $query->orWhereHas('member', fn ($member) => $member->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(member_id) LIKE ?', ["%{$search}%"]))
                        ->orWhereHas('book', fn ($book) => $book->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(book_id) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(isbn) LIKE ?', ["%{$search}%"]))
                        ->orWhereHas('bookCopy', fn ($copy) => $copy->whereRaw('LOWER(accession_number) LIKE ?', ["%{$search}%"]));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper($request->string('status')->toString())))
            ->latest('issue_date')
            ->paginate(15)
            ->withQueryString();
        $routePrefix = $request->routeIs('librarian.*') ? 'librarian' : 'admin';

        return view('admin.returns.index', compact('borrowings', 'routePrefix'));
    }
}
