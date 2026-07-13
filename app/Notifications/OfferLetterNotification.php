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
        $candidate     = $this->offer->application->candidate;
        $firstName     = $candidate->first_name;
        $title         = $this->offer->application->jobPosting->title ?? 'the position';
        $compensation  = number_format($this->offer->compensation, 2);

        $mail = (new MailMessage)
            ->subject("Official Job Offer — {$title}")
            ->greeting("Congratulations, {$firstName}!")
            ->line("We are pleased to formally offer you the position of **{$title}**.")
            ->line("**Compensation:** ₱{$compensation}");

        if ($this->offer->benefits) {
            $mail->line("**Benefits:** {$this->offer->benefits}");
        }

        if ($this->offer->terms) {
            $mail->line("**Terms:** {$this->offer->terms}");
        }

        if ($this->offer->response_deadline) {
            $deadline = \Carbon\Carbon::parse($this->offer->response_deadline)->format('F d, Y');
            $mail->line("Please respond by **{$deadline}**.");
        }

        $mail->line("Please review the details above and reply to confirm your acceptance, or reach out to our HR team with any questions.")
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
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
