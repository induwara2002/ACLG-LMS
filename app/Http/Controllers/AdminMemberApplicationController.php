<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\AuditLogService;
use App\Services\MembershipService;
use App\Services\PradeshiyaSabhaContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminMemberApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $sabhaId = (int) $request->user()->pradeshiya_sabha_id;
        abort_unless($request->user()->isPradeshiyaSabhaAdmin() && $sabhaId > 0, 403);

        $status = strtoupper((string) $request->query('status', 'PENDING'));
        abort_unless(in_array($status, ['PENDING', 'ACTIVE', 'REJECTED', 'SUSPENDED', 'EXPIRED', 'ALL'], true), 404);
        $search = trim((string) $request->query('search', ''));
        $applications = Member::query()
            ->where('pradeshiya_sabha_id', $sabhaId)
            ->with(['membershipType', 'approvedBy', 'rejectedBy'])
            ->when($status !== 'ALL', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($memberQuery) use ($search) {
                $memberQuery->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nic', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%");
            }))
            ->latest('registration_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.member-applications.index', compact('applications', 'search', 'status'));
    }

    public function show(Request $request, int $member): View
    {
        $application = $this->applicationForSabha($request, $member);
        $application->load(['membershipType', 'pradeshiyaSabha', 'approvedBy', 'rejectedBy']);

        return view('admin.member-applications.show', compact('application'));
    }

    public function approve(
        Request $request,
        int $member,
        MembershipService $membershipService,
        PradeshiyaSabhaContext $context,
        AuditLogService $auditLogService
    ): RedirectResponse {
        $application = $this->applicationForSabha($request, $member);
        $context->runForId((int) $application->pradeshiya_sabha_id, function () use (
            $application,
            $request,
            $membershipService,
            $auditLogService
        ): void {
            DB::transaction(function () use ($application, $request, $membershipService, $auditLogService) {
                $lockedMember = Member::query()
                    ->where('pradeshiya_sabha_id', $request->user()->pradeshiya_sabha_id)
                    ->lockForUpdate()
                    ->findOrFail($application->id);
                abort_unless($lockedMember->status === 'PENDING', 409, 'This application has already been reviewed.');

                $membershipStart = today();
                $expiryDate = $membershipStart->copy()
                    ->addMonths($membershipService->membershipDurationMonths());
                $lockedMember->forceFill([
                    'status' => 'ACTIVE',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'membership_start_date' => $membershipStart,
                    'next_renewal_date' => $expiryDate,
                    'expiry_date' => $expiryDate,
                ])->save();

                $auditLogService->record(
                    'member.application.approved',
                    $request->user(),
                    $lockedMember,
                    'Member application approved',
                    ['status' => 'PENDING'],
                    ['status' => 'ACTIVE']
                );
            });
        });

        return redirect()->route('admin.member-applications.show', $application)
            ->with('success', 'Member application approved.');
    }

    public function reject(Request $request, int $member, AuditLogService $auditLogService): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $application = $this->applicationForSabha($request, $member);
        DB::transaction(function () use ($application, $request, $data, $auditLogService): void {
            $lockedMember = Member::query()
                ->where('pradeshiya_sabha_id', $request->user()->pradeshiya_sabha_id)
                ->lockForUpdate()
                ->findOrFail($application->id);
            abort_unless($lockedMember->status === 'PENDING', 409, 'This application has already been reviewed.');

            $lockedMember->forceFill([
                'status' => 'REJECTED',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ])->save();

            $auditLogService->record(
                'member.application.rejected',
                $request->user(),
                $lockedMember,
                'Member application rejected',
                ['status' => 'PENDING'],
                ['status' => 'REJECTED']
            );
        });

        return redirect()->route('admin.member-applications.show', $application)
            ->with('success', 'Member application rejected.');
    }

    private function applicationForSabha(Request $request, int $member): Member
    {
        $user = $request->user();
        $sabhaId = (int) $user->pradeshiya_sabha_id;
        abort_unless($user->isPradeshiyaSabhaAdmin() && $sabhaId > 0, 403);

        return Member::query()
            ->where('pradeshiya_sabha_id', $sabhaId)
            ->findOrFail($member);
    }
}
