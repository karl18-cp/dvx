<?php

namespace App\Mail;

use App\Models\InterviewAppointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InterviewCalendarInvitation extends Mailable
{
    public function __construct(
        public InterviewAppointment $appointment,
        public string $recipientName,
        public string $calendar,
        public string $method = 'REQUEST',
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->method === 'CANCEL' ? 'Cancelled: ' : '';

        return new Envelope(subject: $prefix.'Divertex '.$this->appointment->interview_type.' interview');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.interview-calendar-invitation');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->calendar, 'divertex-interview.ics')
                ->withMime('text/calendar; charset=UTF-8; method='.$this->method),
        ];
    }
}
