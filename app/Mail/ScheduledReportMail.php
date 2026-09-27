<?php

namespace App\Mail;

use App\Models\ReportSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Spec B2's "schedulable for recurring email delivery to a configurable recipient list." */
class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReportSchedule $schedule,
        public string $attachmentContents,
        public string $attachmentFilename,
        public string $attachmentMime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Scheduled report: {$this->schedule->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.scheduled-report', with: ['schedule' => $this->schedule]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->attachmentContents, $this->attachmentFilename)
                ->withMime($this->attachmentMime),
        ];
    }
}
