<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec A1's Email OTP delivery. MAIL_MAILER=log in this dev environment (no
 * SMTP/transactional-email credentials configured) — every send lands in
 * storage/logs/laravel.log instead of an inbox; see PLAN.md.
 */
class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Systems Intelligenz HRIS sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.two-factor-code');
    }
}
