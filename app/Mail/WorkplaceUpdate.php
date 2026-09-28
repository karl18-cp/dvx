<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WorkplaceUpdate extends Mailable
{
    public function __construct(public string $recipientName, public string $heading, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Divertex — '.mb_substr($this->heading, 0, 180));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.workplace-update');
    }
}
