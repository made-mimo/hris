<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec Section A1's self-service password reset: "emailed, single-use,
 * time-expiring reset codes." Sent synchronously, same reasoning as
 * TwoFactorCodeMail — the user is waiting on this screen right now.
 */
class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your Systems Intelligenz HRIS password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-code');
    }
}
