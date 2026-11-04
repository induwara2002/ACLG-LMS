<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Scopes\PradeshiyaSabhaScope;
use App\Services\EmailAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMembershipExpiryReminders extends Command
{
    protected $signature = 'library:remind-membership-expiry';

    protected $description = 'Send reminders for active memberships approaching expiry';

    public function handle(EmailAlertService $emailAlerts): int
    {
        $days = (int) config('library.email_alerts.membership_expiry_reminder_days', 7);
        if ($days < 0) {
            $this->error('LIBRARY_MEMBERSHIP_EXPIRY_REMINDER_DAYS must be zero or greater.');

            return self::FAILURE;
        }

        $targetDate = Carbon::today()->addDays($days);
        $sent = 0;

        Member::withoutGlobalScope(PradeshiyaSabhaScope::class)
            ->with([
                'membershipType',
                'pradeshiyaSabha',
            ])
            ->whereRaw('upper(status) = ?', ['ACTIVE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', Carbon::today())
            ->whereDate('expiry_date', $targetDate)
            ->chunkById(100, function ($members) use ($emailAlerts, &$sent) {
                foreach ($members as $member) {
                    if ($emailAlerts->sendReminderOnce(
                        'membership_expiry_reminder',
                        'member',
                        (int) $member->id,
                        $member->expiry_date,
                        fn () => $emailAlerts->sendMembershipExpiryReminder($member),
                    )) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} membership expiry reminder(s) for {$targetDate->toDateString()}.");

        return self::SUCCESS;
    }
}
