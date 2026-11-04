<?php

namespace App\Services;

use App\Mail\LibraryAlertMail;
use App\Models\Borrowing;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailAlertService
{
    public function sendMemberRegistration(Member $member): void
    {
        $member->loadMissing(['membershipType', 'pradeshiyaSabha']);

        $this->send(
            $member->email,
            new LibraryAlertMail(
                'member_registered',
                $member->full_name,
                $member->pradeshiyaSabha?->name ?? config('app.name'),
                array_filter([
                    'Member ID' => $member->member_id,
                    'Membership type' => $member->membershipType?->name,
                    'Membership status' => $member->effectiveStatus(),
                    'Membership expires' => $member->expiry_date?->format('F j, Y'),
                ], fn ($value) => $value !== null && $value !== ''),
            ),
            'member_registered',
            'member',
            (int) $member->id,
        );
    }

    public function sendBookIssue(Borrowing $borrowing): void
    {
        $borrowing->loadMissing(['member', 'book', 'pradeshiyaSabha']);
        $member = $borrowing->member;

        if (! $member) {
            Log::warning('Book issue email skipped because the borrowing has no member.', [
                'alert_type' => 'book_issued',
                'borrowing_id' => $borrowing->id,
            ]);

            return;
        }

        $details = [
            'Book' => $borrowing->book?->title ?? 'Book',
            'Book ID' => $borrowing->book?->book_id,
            'ISBN' => $borrowing->book?->isbn,
            'Issue date' => $borrowing->issue_date?->format('F j, Y'),
            'Return due date' => $borrowing->due_date?->format('F j, Y'),
        ];

        $this->send(
            $member->email,
            new LibraryAlertMail(
                'book_issued',
                $member->full_name,
                $borrowing->pradeshiyaSabha?->name ?? config('app.name'),
                array_filter($details, fn ($value) => $value !== null && $value !== ''),
            ),
            'book_issued',
            'borrowing',
            (int) $borrowing->id,
        );
    }

    public function sendDueDateReminder(Borrowing $borrowing): bool
    {
        $borrowing->loadMissing(['member', 'book', 'pradeshiyaSabha']);
        $member = $borrowing->member;

        if (! $member) {
            Log::warning('Due-date reminder skipped because the borrowing has no member.', [
                'alert_type' => 'due_date_reminder',
                'borrowing_id' => $borrowing->id,
            ]);

            return false;
        }

        return $this->send(
            $member->email,
            new LibraryAlertMail(
                'due_date_reminder',
                $member->full_name,
                $borrowing->pradeshiyaSabha?->name ?? config('app.name'),
                array_filter([
                    'Book' => $borrowing->book?->title,
                    'Book ID' => $borrowing->book?->book_id,
                    'Return due date' => $borrowing->due_date?->format('F j, Y'),
                ], fn ($value) => $value !== null && $value !== ''),
            ),
            'due_date_reminder',
            'borrowing',
            (int) $borrowing->id,
        );
    }

    public function sendMembershipExpiryReminder(Member $member): bool
    {
        $member->loadMissing(['membershipType', 'pradeshiyaSabha']);

        return $this->send(
            $member->email,
            new LibraryAlertMail(
                'membership_expiry_reminder',
                $member->full_name,
                $member->pradeshiyaSabha?->name ?? config('app.name'),
                array_filter([
                    'Member ID' => $member->member_id,
                    'Membership type' => $member->membershipType?->name,
                    'Membership expires' => $member->expiry_date?->format('F j, Y'),
                ], fn ($value) => $value !== null && $value !== ''),
            ),
            'membership_expiry_reminder',
            'member',
            (int) $member->id,
        );
    }

    public function sendReminderOnce(
        string $alertType,
        string $entityType,
        int $entityId,
        CarbonInterface $periodDate,
        callable $send,
    ): bool {
        $now = now();
        $inserted = DB::table('email_alert_deliveries')->insertOrIgnore([
            'alert_type' => $alertType,
            'alertable_type' => $entityType,
            'alertable_id' => $entityId,
            'period_date' => $periodDate->toDateString(),
            'reserved_at' => $now,
            'sent_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted !== 1) {
            return false;
        }

        if (! $send()) {
            DB::table('email_alert_deliveries')
                ->where('alert_type', $alertType)
                ->where('alertable_type', $entityType)
                ->where('alertable_id', $entityId)
                ->whereDate('period_date', $periodDate->toDateString())
                ->whereNull('sent_at')
                ->delete();

            return false;
        }

        DB::table('email_alert_deliveries')
            ->where('alert_type', $alertType)
            ->where('alertable_type', $entityType)
            ->where('alertable_id', $entityId)
            ->whereDate('period_date', $periodDate->toDateString())
            ->update(['sent_at' => now(), 'updated_at' => now()]);

        return true;
    }

    private function send(
        ?string $email,
        Mailable $mail,
        string $alertType,
        string $entityType,
        int $entityId,
    ): bool {
        if (! config('library.email_alerts.enabled', true)) {
            return false;
        }

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Library email alert skipped because the recipient address is missing or invalid.', [
                'alert_type' => $alertType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $exception) {
            Log::error('Library email alert could not be sent.', [
                'alert_type' => $alertType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'exception' => $exception::class,
            ]);

            return false;
        }

        return true;
    }
}
