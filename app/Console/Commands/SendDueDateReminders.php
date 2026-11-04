<?php

namespace App\Console\Commands;

use App\Models\Borrowing;
use App\Models\Scopes\PradeshiyaSabhaScope;
use App\Services\EmailAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDueDateReminders extends Command
{
    protected $signature = 'library:remind-loans';

    protected $description = 'Send reminders for active loans approaching their return due date';

    public function handle(EmailAlertService $emailAlerts): int
    {
        $days = (int) config('library.email_alerts.due_date_reminder_days', 2);
        if ($days < 0) {
            $this->error('LIBRARY_DUE_DATE_REMINDER_DAYS must be zero or greater.');

            return self::FAILURE;
        }

        $targetDate = Carbon::today()->addDays($days);
        $sent = 0;

        Borrowing::withoutGlobalScope(PradeshiyaSabhaScope::class)
            ->with([
                'member' => fn ($query) => $query->withoutGlobalScope(PradeshiyaSabhaScope::class),
                'book' => fn ($query) => $query->withoutGlobalScope(PradeshiyaSabhaScope::class),
                'pradeshiyaSabha',
            ])
            ->whereNull('return_date')
            ->whereIn('status', ['ISSUED', 'issued'])
            ->whereDate('due_date', $targetDate)
            ->chunkById(100, function ($borrowings) use ($emailAlerts, &$sent) {
                foreach ($borrowings as $borrowing) {
                    if ($emailAlerts->sendReminderOnce(
                        'due_date_reminder',
                        'borrowing',
                        (int) $borrowing->id,
                        $borrowing->due_date,
                        fn () => $emailAlerts->sendDueDateReminder($borrowing),
                    )) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} due-date reminder(s) for {$targetDate->toDateString()}.");

        return self::SUCCESS;
    }
}
