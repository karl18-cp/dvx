<?php

namespace App\Mail;

use App\Models\BusinessInquiry;
use Illuminate\Mail\Mailable;

class BusinessInquiryReceived extends Mailable
{
    public function __construct(public BusinessInquiry $inquiry) {}

    public function build(): static
    {
        return $this->subject('Divertex — new business meeting request #'.$this->inquiry->id)
            ->replyTo($this->inquiry->email, $this->inquiry->name)
            ->view('emails.business-inquiry');
    }
}
