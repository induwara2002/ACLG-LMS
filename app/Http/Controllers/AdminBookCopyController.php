<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookCopyRequest;
use App\Http\Requests\UpdateBookCopyRequest;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Http\Request;

class AdminBookCopyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $bookCopies = BookCopy::with('book')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('accession_number', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('book', function ($bookQuery) use ($search) {
                            $bookQuery->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.book_copies.index', compact('bookCopies', 'search'));
    }

    public function create()
    {
        return view('admin.book_copies.create', [
            'books' => Book::orderBy('title')->get(),
        ]);
    }

    public function store(StoreBookCopyRequest $request)
    {
        BookCopy::create($request->validated());

        return redirect()->route('admin.book-copies.index')->with('success', 'Book copy created successfully.');
    }

    public function show(BookCopy $bookCopy)
    {
        $bookCopy->load('book');

        return view('admin.book_copies.show', compact('bookCopy'));
    }

    public function edit(BookCopy $bookCopy)
    {
        return view('admin.book_copies.edit', [
            'bookCopy' => $bookCopy,
            'books' => Book::orderBy('title')->get(),
        ]);
    }

    public function update(UpdateBookCopyRequest $request, BookCopy $bookCopy)
    {
        $bookCopy->update($request->validated());

        return redirect()->route('admin.book-copies.show', $bookCopy)->with('success', 'Book copy updated successfully.');
    }

    public function destroy(BookCopy $bookCopy)
    {
        if ($bookCopy->borrowings()->exists()) {
            return redirect()->route('admin.book-copies.index')->with('error', 'Cannot delete this book copy because historical borrowing records exist.');
        }

        $bookCopy->delete();

        return redirect()->route('admin.book-copies.index')->with('success', 'Book copy deleted successfully.');
    }
}
