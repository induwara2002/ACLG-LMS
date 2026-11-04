<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class LibraryAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $alertType,
        public readonly string $memberName,
        public readonly string $libraryName,
        public readonly array $details,
    ) {
        if (! in_array($alertType, [
            'member_registered',
            'book_issued',
            'due_date_reminder',
            'membership_expiry_reminder',
        ], true)) {
            throw new InvalidArgumentException('Unsupported library email alert type.');
        }
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->alertType) {
            'member_registered' => 'Welcome to '.$this->libraryName,
            'book_issued' => 'Book issued by '.$this->libraryName,
            'due_date_reminder' => 'Book return reminder from '.$this->libraryName,
            'membership_expiry_reminder' => 'Membership expiry reminder from '.$this->libraryName,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.library-alert',
            with: [
                'alertType' => $this->alertType,
                'memberName' => $this->memberName,
                'libraryName' => $this->libraryName,
                'details' => $this->details,
            ],
        );
    }
}
