<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmployeeWelcome extends Mailable
{
    public function __construct(
        public string $employeeName,
        public string $employeeId,
        public string $temporaryPassword,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to Divertex — your account credentials');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.employee-welcome');
    }
}
