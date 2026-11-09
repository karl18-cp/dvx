<?php

namespace App\Mail;

use App\Models\EmployeeDocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeDocumentReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmployeeDocumentRequest $documentRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your requested HR document is ready');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.employee-document-ready');
    }

    public function attachments(): array
    {
        return [Attachment::fromStorageDisk($this->documentRequest->file_disk, $this->documentRequest->file_path)
            ->as($this->documentRequest->original_filename)->withMime($this->documentRequest->mime_type)];
    }
}
