<?php

namespace App\Notifications;

use App\Models\JobOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferLetterNotification extends Notification
{
    use Queueable;

    public JobOffer $offer;

    public function __construct(JobOffer $offer)
    {
        $this->offer = $offer;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // -------------------------------------------------------------------------
    // EMAIL
    // -------------------------------------------------------------------------

    public function toMail(object $notifiable): MailMessage
    {
        $candidate    = $this->offer->application->candidate;
        $jobTitle     = $this->offer->application->jobPosting->title ?? 'the position';
        $compensation = number_format($this->offer->compensation, 2);
        $deadline     = $this->offer->response_deadline
            ? \Carbon\Carbon::parse($this->offer->response_deadline)->format('F d, Y')
            : null;

        return (new MailMessage)
            ->subject("Official Job Offer — {$jobTitle}")
            ->view('mail.offer-letter', [
                'candidate'    => $candidate,
                'offer'        => $this->offer,
                'jobTitle'     => $jobTitle,
                'compensation' => $compensation,
                'deadline'     => $deadline,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'job_offer_id'   => $this->offer->id,
            'application_id' => $this->offer->application_id,
            'compensation'   => $this->offer->compensation,
        ];
    }
}
