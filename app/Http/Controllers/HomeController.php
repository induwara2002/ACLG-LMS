<?php

namespace App\Http\Controllers;

use App\Models\Book;

class HomeController extends Controller
{
    public function index()
    {
        $featuredBooks = Book::query()
            ->with('author')
            ->latest()
            ->limit(4)
            ->get();
        $bookCount = Book::query()->count();

        return view('home', compact('featuredBooks', 'bookCount'));
    }
}
