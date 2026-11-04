<?php

namespace App\Http\Controllers;

use App\Exceptions\PradeshiyaSabhaConfigurationIncompleteException;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\PradeshiyaSabhaSettingsService;
use App\Services\PradeshiyaSabhaContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class AdminMemberController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $membershipTypeId = $request->query('membership_type_id');

        $members = Member::with('membershipType')
            ->withCount([
                'borrowings as active_borrowings_count' => fn ($query) => $query->active(),
                'fines as unpaid_fines_count' => fn ($query) => $query->where('status', 'UNPAID'),
            ])
            ->withSum([
                'fines as unpaid_fines_total' => fn ($query) => $query->where('status', 'UNPAID'),
            ], 'amount')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('member_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nic', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($membershipTypeId, fn ($query) => $query->where('membership_type_id', $membershipTypeId))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'total' => Member::count(),
            'active' => Member::where('status', 'ACTIVE')->where(function ($query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
            })->count(),
            'expired' => Member::where(function ($query) {
                $query->where('status', 'EXPIRED')->orWhere(function ($query) {
                    $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today());
                });
            })->count(),
            'suspended' => Member::where('status', 'SUSPENDED')->count(),
            'children' => Member::whereHas('membershipType', fn ($query) => $query->whereRaw('upper(name) = ?', ['CHILDREN']))->count(),
            'elders' => Member::whereHas('membershipType', fn ($query) => $query->whereRaw('upper(name) = ?', ['ELDERS']))->count(),
        ];
        $membershipTypes = MembershipType::query()->assignable()->orderBy('name')->get();

        return view('admin.members.index', compact('members', 'search', 'status', 'membershipTypeId', 'membershipTypes', 'summary'));
    }

    public function create()
    {
        return view('admin.members.create', [
            'membershipTypes' => MembershipType::query()->assignable()->orderBy('name')->get(),
            'sabhas' => auth()->user()->isMainAdmin()
                ? PradeshiyaSabha::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'activeSabhaId' => app(PradeshiyaSabhaContext::class)->id(),
        ]);
    }

    public function store(StoreMemberRequest $request, PradeshiyaSabhaContext $context)
    {
        $data = $request->validated();
        $password = $data['password'];
        $sabhaId = $context->id();
        unset($data['password']);
        unset($data['sabha_id']);
        abort_unless($request->user()->isPradeshiyaSabhaAdmin() && $sabhaId !== null, 403);
        $sabha = PradeshiyaSabha::query()->where('is_active', true)->findOrFail($sabhaId);

        $createMember = function () use ($data, $password, $sabhaId) {
            app(PradeshiyaSabhaSettingsService::class)
                ->assertConfigured(PradeshiyaSabha::query()->findOrFail($sabhaId));

            $date = Carbon::today();
            $membershipService = app(MembershipService::class);
            $renewalDate = $date->copy()->addMonths($membershipService->membershipDurationMonths());
            $data['registration_date'] = $date;
            $data['membership_start_date'] = $date;
            $data['next_renewal_date'] = $renewalDate;
            $data['expiry_date'] = $renewalDate;
            $data['status'] = 'ACTIVE';
            $data['registration_fee'] = app(\App\Services\PaymentService::class)->registrationFee();

            return DB::transaction(function () use ($data, $password, $sabhaId) {
                $user = User::create([
                    'name' => $data['full_name'],
                    'email' => $data['email'],
                    'password' => Hash::make($password),
                    'role' => User::ROLE_MEMBER,
                    'is_active' => true,
                    'pradeshiya_sabha_id' => $sabhaId,
                ]);
                $data['user_id'] = $user->id;
                $data['pradeshiya_sabha_id'] = $sabhaId;
                $member = Member::create($data);
                app(MembershipService::class)->registerPayment($member);

                return $member->load(['membershipType', 'pradeshiyaSabha']);
            });
        };

        try {
            $member = $context->runFor($sabha, $createMember);
        } catch (PradeshiyaSabhaConfigurationIncompleteException $exception) {
            return back()->withErrors(['settings' => $exception->getMessage()])->withInput();
        }

        app(\App\Services\EmailAlertService::class)->sendMemberRegistration($member);

        return redirect()->route('admin.members.show', $member)
            ->with('success', 'Member created successfully.');
    }

    public function show(Member $member)
    {
        $member->load([
            'membershipType',
            'membershipPayments' => fn ($query) => $query->latest('payment_date'),
            'borrowings' => fn ($query) => $query->with(['book', 'bookCopy'])->latest('issue_date'),
            'fines' => fn ($query) => $query->latest('issued_date'),
            'payments' => fn ($query) => $query->latest('payment_date'),
        ]);

        return view('admin.members.show', compact('member'));
    }

    public function edit(Member $member)
    {
        $member->load('membershipType');

        return view('admin.members.edit', [
            'member' => $member,
            'membershipTypes' => MembershipType::query()->assignable()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $data = $request->validated();
        DB::transaction(function () use ($member, $data) {
            if ($member->user) {
                $member->user->update([
                    'name' => $data['full_name'],
                    'email' => $data['email'],
                ]);
            }
            $member->update($data);
        });

        return redirect()->route('admin.members.show', $member)
            ->with('success', 'Member updated successfully.');
    }

    public function destroy(Member $member)
    {
        if ($member->borrowings()->exists() || $member->payments()->exists() || $member->fines()->exists()) {
            return redirect()->route('admin.members.index')->with('error', 'Cannot delete this member because historical borrowing and payment records exist.');
        }

        $member->delete();

        return redirect()->route('admin.members.index')->with('success', 'Member deleted successfully.');
    }

    public function suspend(Member $member)
    {
        $member->update(['status' => 'SUSPENDED']);

        return back()->with('success', 'Member suspended successfully.');
    }

    public function reactivate(Member $member)
    {
        if ($member->status !== 'SUSPENDED') {
            return back()->with('error', 'Only suspended members can be reactivated.');
        }

        if ($member->effectiveStatus() === 'EXPIRED') {
            return back()->with('error', 'An expired member must complete renewal before reactivation.');
        }

        $member->update(['status' => 'ACTIVE']);

        return back()->with('success', 'Member reactivated successfully.');
    }

    public function renew(Member $member, MembershipService $membershipService)
    {
        if (! in_array($member->status, ['ACTIVE', 'EXPIRED'], true)) {
            return back()->with('error', 'Only active or expired memberships can be renewed.');
        }

        $membershipService->renew($member);

        return back()->with('success', 'Membership renewed successfully.');
    }
}
