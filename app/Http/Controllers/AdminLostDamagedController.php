<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDamageRecordRequest;
use App\Http\Requests\StoreLostBookRequest;
use App\Models\DamageRecord;
use App\Models\LostBook;
use App\Models\Member;
use App\Models\Borrowing;
use App\Models\BookCopy;
use App\Services\DamageRecordService;
use App\Services\LostBookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AdminLostDamagedController extends Controller
{
    public function index(Request $request)
    {
        $lostBooks = LostBook::with(['member', 'book', 'bookCopy'])
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', (int) $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper($request->string('status')->toString())))
            ->latest('lost_date')
            ->paginate(15)
            ->withQueryString();

        $damageRecords = DamageRecord::with(['member', 'book', 'bookCopy'])
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', (int) $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper($request->string('status')->toString())))
            ->latest('damage_date')
            ->paginate(15)
            ->withQueryString();

        $members = Member::orderBy('full_name')->get();

        return view('admin.lost-damaged.index', compact('lostBooks', 'damageRecords', 'members'));
    }

    public function createLost()
    {
        return view('admin.lost-damaged.create-lost', ['members' => Member::orderBy('full_name')->get()]);
    }

    public function createDamaged()
    {
        return view('admin.lost-damaged.create-damaged', ['members' => Member::orderBy('full_name')->get()]);
    }

    public function storeLost(StoreLostBookRequest $request, LostBookService $service)
    {
        try {
            $service->reportLost(
                Member::findOrFail($request->integer('member_id')),
                Borrowing::findOrFail($request->integer('borrowing_id')),
                BookCopy::findOrFail($request->integer('book_copy_id')),
                $request->input('lost_date'),
                $request->string('compensation_amount')->toString(),
                $request->input('notes'),
                Auth::user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['lost_book' => $exception->getMessage()])->withInput();
        }

        return redirect()->route($request->routeIs('librarian.*') ? 'librarian.lost-damaged.index' : 'admin.lost-damaged.index')
            ->with('success', 'Lost book record created successfully.');
    }

    public function storeDamaged(StoreDamageRecordRequest $request, DamageRecordService $service)
    {
        try {
            $service->reportDamage(
                Member::findOrFail($request->integer('member_id')),
                Borrowing::findOrFail($request->integer('borrowing_id')),
                BookCopy::findOrFail($request->integer('book_copy_id')),
                $request->string('damage_type')->toString(),
                $request->input('damage_date'),
                $request->string('compensation_amount')->toString(),
                $request->string('description')->toString(),
                Auth::user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['damage_record' => $exception->getMessage()])->withInput();
        }

        return redirect()->route($request->routeIs('librarian.*') ? 'librarian.lost-damaged.index' : 'admin.lost-damaged.index')
            ->with('success', 'Damaged book record created successfully.');
    }

    public function payLost(LostBook $lostBook, LostBookService $service)
    {
        try {
            $service->payCompensation($lostBook);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['lost_book' => $exception->getMessage()]);
        }

        return back()->with('success', 'Lost book compensation recorded successfully.');
    }

    public function payDamaged(DamageRecord $damageRecord, DamageRecordService $service)
    {
        try {
            $service->payCompensation($damageRecord);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['damage_record' => $exception->getMessage()]);
        }

        return back()->with('success', 'Damage compensation recorded successfully.');
    }
}
