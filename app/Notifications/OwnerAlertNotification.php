<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * "Somebody just engaged with the site."
 *
 * One class for every owner alert, rather than one per event, because the
 * alerts differ only in their words. Four of the highest intent actions on the
 * site used to be silent: a registration, a waitlist signup, a contact message
 * and, for a while, anything from a visitor with no account. The owner found
 * out by opening the admin and looking.
 *
 * Queued, because these are sent during a visitor's request and SMTP on a
 * shared host is slow and sometimes unreachable. With the sync queue driver
 * that still happens inline, which is why fire() swallows and logs rather than
 * throws: a mail server having a bad minute must never turn somebody's
 * successful registration into an error page.
 *
 * Reply-To is the person who wrote in, so hitting reply in any mail client
 * answers them rather than the site's own mailbox. That single header is the
 * difference between an alert you can act on and one you have to copy an
 * address out of.
 */
class OwnerAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string,string>  $rows
     */
    public function __construct(
        private readonly string $subject,
        private readonly string $headline,
        private readonly array $rows = [],
        private readonly ?string $body = null,
        private readonly ?string $replyTo = null,
        private readonly ?string $actionLabel = null,
        private readonly ?string $actionUrl = null,
    ) {}

    /**
     * Send to the configured owner address.
     *
     * Static because every caller wants the same thing and none of them should
     * have to know how the owner is addressed, or remember to guard the send.
     *
     * @param  array<string,string>  $rows
     */
    public static function fire(
        string $subject,
        string $headline,
        array $rows = [],
        ?string $body = null,
        ?string $replyTo = null,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
    ): void {
        $to = config('company.owner_alerts');

        if (! is_string($to) || trim($to) === '') {
            return;
        }

        try {
            Notifier::route('mail', $to)->notify(new self(
                $subject, $headline, $rows, $body, $replyTo, $actionLabel, $actionUrl,
            ));
        } catch (\Throwable $e) {
            // The visitor's action already succeeded. Losing the alert is bad;
            // turning their success into a 500 because SMTP timed out is worse.
            Log::warning('Owner alert could not be sent', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting($this->headline);

        if ($this->replyTo !== null) {
            $mail->replyTo($this->replyTo);
        }

        foreach ($this->rows as $label => $value) {
            $mail->line('**'.$label.':** '.$value);
        }

        if ($this->body !== null && trim($this->body) !== '') {
            $mail->line('---')->line($this->body);
        }

        if ($this->actionLabel !== null && $this->actionUrl !== null) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        return $mail->salutation('Sent by '.config('app.name').'.');
    }
}
