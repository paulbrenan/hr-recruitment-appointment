<?php

namespace App\Mail;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Candidate $candidate,
        public readonly string    $transactionNumber,
        public readonly string    $position,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'DepEd Cavite – Application Received (' . $this->transactionNumber . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.application-submitted',
        );
    }
}