<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApplicantSubmission extends Mailable
{
    public function __construct(public JobApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->application->email)],
            subject: 'Divertex application #'.$this->application->id.' — '.$this->application->position,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.applicant-submission');
    }

    public function attachments(): array
    {
        if (! $this->application->resume_path) {
            return [];
        }
        $extension = match ($this->application->resume_mime) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            default => 'docx',
        };

        return [Attachment::fromStorageDisk($this->application->resume_disk, $this->application->resume_path)
            ->as('resume-'.$this->application->id.'.'.$extension)
            ->withMime($this->application->resume_mime)];
    }
}
