<?php
/**
 * fix_restyle_offer_letter.php
 *
 * Rewires OfferLetterNotification to render through the new DepEd-branded
 * view (resources/views/mail/offer-letter.blade.php) instead of the
 * default markdown line()/greeting() chain, matching every other email
 * restyled today.
 *
 * REQUIRES offer-letter.blade.php copied into resources/views/mail/ FIRST.
 *
 * HOW TO RUN:
 *   1. Copy offer-letter.blade.php into resources/views/mail/
 *   2. php fix_restyle_offer_letter.php   (from project root)
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) { echo "\n❌ File not found: $path\n"; exit(1); }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== fix_restyle_offer_letter.php ===\n\n";

$path = ROOT . '/app/Notifications/OfferLetterNotification.php';

echo "[1] Patching OfferLetterNotification::toMail()...\n";

apply_patch(
    $path,
    'public function toMail(object $notifiable): MailMessage
    {
        $candidate     = $this->offer->application->candidate;
        $firstName     = $candidate->first_name;
        $title         = $this->offer->application->jobPosting->title ?? \'the position\';
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
            $deadline = \Carbon\Carbon::parse($this->offer->response_deadline)->format(\'F d, Y\');
            $mail->line("Please respond by **{$deadline}**.");
        }

        $mail->line("Please review the details above and reply to confirm your acceptance, or reach out to our HR team with any questions.")
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }',
    'public function toMail(object $notifiable): MailMessage
    {
        $candidate    = $this->offer->application->candidate;
        $jobTitle     = $this->offer->application->jobPosting->title ?? \'the position\';
        $compensation = number_format($this->offer->compensation, 2);
        $deadline     = $this->offer->response_deadline
            ? \Carbon\Carbon::parse($this->offer->response_deadline)->format(\'F d, Y\')
            : null;

        return (new MailMessage)
            ->subject("Official Job Offer — {$jobTitle}")
            ->view(\'mail.offer-letter\', [
                \'candidate\'    => $candidate,
                \'offer\'        => $this->offer,
                \'jobTitle\'     => $jobTitle,
                \'compensation\' => $compensation,
                \'deadline\'     => $deadline,
            ]);
    }',
    'OfferLetterNotification: uses mail.offer-letter view'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Offer letter email now uses the same DepEd-branded template as\n";
echo "    every other email restyled today.\n";
echo "  - Functionally unchanged otherwise -- same data, same conditional\n";
echo "    benefits/terms/deadline lines.\n\n";
echo "DELETE this script after running.\n";
