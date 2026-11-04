<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFineRequest;
use App\Models\Fine;
use App\Models\Member;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminFineController extends Controller
{
    public function index(Request $request)
    {
        $search = mb_strtolower(trim($request->string('search')->toString()));
        $fines = Fine::with(['member', 'borrowing.book'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper($request->string('status')->toString())))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('member', fn ($member) => $member->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(member_id) LIKE ?', ["%{$search}%"]))
                        ->orWhereHas('borrowing.book', fn ($book) => $book->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"]))
                        ->orWhereRaw('LOWER(fine_type) LIKE ?', ["%{$search}%"]);
                });
            })
            ->latest('issued_date')->paginate(15)->withQueryString();
        $fineSummary = [
            'unpaid' => Fine::where('status', 'UNPAID')->sum('amount'),
            'paid' => Fine::where('status', 'PAID')->sum('amount'),
            'waived' => Fine::where('status', 'WAIVED')->sum('amount'),
        ];
        return view('admin.fines.index', compact('fines', 'fineSummary'));
    }

    public function create()
    {
        return view('admin.fines.create', ['members' => Member::orderBy('full_name')->get()]);
    }

    public function show(Fine $fine)
    {
        $fine->load(['member', 'borrowing.book', 'borrowing.bookCopy', 'payments']);

        return view('admin.fines.show', compact('fine'));
    }

    public function store(StoreFineRequest $request, PaymentService $service)
    {
        try {
            $service->createFine(
                Member::findOrFail($request->integer('member_id')),
                $request->string('amount')->toString(),
                $request->string('fine_type')->toString(),
                $request->integer('borrowing_id') ?: null,
                $request->input('description')
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['fine' => $exception->getMessage()])->withInput();
        }

        return redirect()->route($request->routeIs('librarian.*') ? 'librarian.fines.index' : 'admin.fines.index')->with('success', 'Fine created successfully.');
    }

    public function pay(Fine $fine, PaymentService $service)
    {
        try {
            $service->payFine($fine);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['fine' => $exception->getMessage()]);
        }
        return back()->with('success', 'Fine payment recorded successfully.');
    }

    public function waive(Fine $fine, PaymentService $service)
    {
        try {
            $service->waiveFine($fine);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['fine' => $exception->getMessage()]);
        }

        return back()->with('success', 'Fine waived successfully.');
    }
}
