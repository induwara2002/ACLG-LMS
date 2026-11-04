<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MembershipService
{
    public const REGISTRATION_FEE = '220.00';
    public const MONTHLY_FEE = '120.00';
    public const LATE_RENEWAL_FEE = '180.00';

    public function effectiveStatus(Member $member): string
    {
        return $member->effectiveStatus();
    }

    public function membershipDurationMonths(): int
    {
        return app(PaymentService::class)->integerSetting('membership_duration_months', 12);
    }

    public function registerPayment(Member $member, string $reference = null): MembershipPayment
    {
        return app(PaymentService::class)->recordMembership($member, 'REGISTRATION', $reference);
    }

    public function renew(Member $member, string $reference = null): MembershipPayment
    {
        return app(PradeshiyaSabhaContext::class)->runForId(
            $member->pradeshiya_sabha_id === null ? null : (int) $member->pradeshiya_sabha_id,
            fn () => DB::transaction(function () use ($member, $reference) {
            $status = $this->effectiveStatus($member);
            $late = $status === 'EXPIRED';
            $payment = app(PaymentService::class)->recordMembership(
                $member,
                $late ? 'LATE_RENEWAL' : 'MONTHLY_MEMBERSHIP',
                $reference
            );

            $durationMonths = $this->membershipDurationMonths();
            $baseDate = $member->next_renewal_date && Carbon::parse($member->next_renewal_date)->isFuture()
                ? Carbon::parse($member->next_renewal_date)
                : Carbon::today();

            $member->update([
                'next_renewal_date' => $baseDate->copy()->addMonths($durationMonths)->toDateString(),
                'expiry_date' => $baseDate->copy()->addMonths($durationMonths)->toDateString(),
                'status' => 'ACTIVE',
            ]);

            return $payment;
        })
        );
    }
}
