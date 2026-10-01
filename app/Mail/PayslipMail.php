<?php

namespace App\Mail;

use App\Models\AccountingEntry;
use App\Services\PayslipPdfService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

class PayslipMail extends Mailable
{
    public function __construct(
        public AccountingEntry $entry,
        public string $pdf,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Divertex payslip - '.Carbon::parse($this->entry->period_start)->format('M d').' to '.Carbon::parse($this->entry->period_end)->format('M d, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payslip', with: [
            'companyName' => PayslipPdfService::COMPANY_NAME,
            'employeeName' => $this->entry->employee->name,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
