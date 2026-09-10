<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpMail extends Mailable
{
    public function __construct(
        public string $code,
        public string $type,
        public string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->type === 'password_reset'
            ? "Reset your {$this->siteName} password"
            : "Verify your {$this->siteName} email";

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.otp');
    }
}
