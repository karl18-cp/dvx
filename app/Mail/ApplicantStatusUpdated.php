<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApplicantStatusUpdated extends Mailable
{
    public function __construct(
        public string $applicantName,
        public string $position,
        public string $statusLabel,
        public ?string $publicUpdate,
        public string $portalUrl,
        public ?string $scheduleLabel = null,
        public ?string $scheduledStart = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Divertex application update — '.$this->statusLabel);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.applicant-status-updated');
    }
}
