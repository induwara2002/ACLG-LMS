<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordMembershipPaymentRequest;
use App\Models\Member;
use App\Models\MembershipPayment;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminPaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with('member')
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', (int) $request->input('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper($request->string('status')->toString())))
            ->when($request->filled('payment_type'), fn ($query) => $query->where('payment_type', strtoupper($request->string('payment_type')->toString())))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('payment_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('payment_date', '<=', $request->input('date_to')))
            ->latest('payment_date')->paginate(15)->withQueryString();

        $membershipPayments = MembershipPayment::with('member')
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', (int) $request->input('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper($request->string('status')->toString())))
            ->when($request->filled('payment_type'), fn ($query) => $query->where('payment_type', strtoupper($request->string('payment_type')->toString())))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('payment_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('payment_date', '<=', $request->input('date_to')))
            ->latest('payment_date')->limit(100)->get();

        $members = Member::orderBy('full_name')->get();

        return view('admin.payments.index', compact('payments', 'membershipPayments', 'members'));
    }

    public function show(Payment $payment)
    {
        $payment->load(['member', 'borrowing.book', 'borrowing.bookCopy', 'fine']);

        return view('admin.payments.show', compact('payment'));
    }

    public function storeMembership(RecordMembershipPaymentRequest $request, PaymentService $service)
    {
        try {
            $service->recordMembership(Member::findOrFail($request->integer('member_id')), $request->string('payment_type')->toString(), $request->input('reference'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Membership payment recorded successfully.');
    }
}
