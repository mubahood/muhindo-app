<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one email and says exactly what happened.
 *
 * Mail is the one part of an application that fails silently by design: a
 * queued notification that cannot connect looks identical, from the outside, to
 * one nobody has triggered yet. The first anyone hears is a customer saying
 * they never got a password reset.
 *
 * This exists so the answer to "is mail working" takes ten seconds and needs no
 * code, and so the common misconfigurations are named rather than left as a
 * Symfony exception to interpret.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {to? : Where to send it, defaults to the from address}';

    protected $description = 'Send a test email and report what the mail server said';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $to = (string) ($this->argument('to') ?: $from);

        $this->line('  mailer   '.$mailer);

        if ($mailer === 'smtp') {
            $this->line('  host     '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
            $this->line('  scheme   '.(config('mail.mailers.smtp.scheme') ?: 'smtp (STARTTLS)'));
            $this->line('  username '.(config('mail.mailers.smtp.username') ?: '(none)'));
            $this->line('  password '.(config('mail.mailers.smtp.password') ? 'set' : 'NOT SET'));
        }

        $this->line('  from     '.$from);
        $this->line('  to       '.$to);
        $this->newLine();

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER is "log": nothing is delivered, it is written to storage/logs/laravel.log.');
        }

        if ($mailer === 'smtp' && ! config('mail.mailers.smtp.password')) {
            $this->error('MAIL_PASSWORD is empty. Set the mailbox password in .env, then run this again.');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'This is a test from '.config('app.name').".\n\n"
                .'If you are reading it, outgoing mail works: password resets, '
                ."invoices and notifications will reach people.\n\n"
                .'Sent '.now()->format('D d M Y, H:i:s T').'.',
                fn ($message) => $message->to($to)->subject('Mail test from '.config('app.name'))
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Failed: '.$e->getMessage());
            $this->newLine();
            $this->explain($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent. Check '.$to.', including the spam folder on the first send.');

        return self::SUCCESS;
    }

    /**
     * Turn the four failures that actually happen into an instruction.
     *
     * Every one of these has been mistaken for "the server is broken" by
     * somebody who then changed the wrong setting.
     */
    private function explain(string $error): void
    {
        $error = strtolower($error);

        $hint = match (true) {
            str_contains($error, 'authentication') || str_contains($error, '535') => 'The server rejected the username or password. The username is the FULL address '
                .'(noreply@muhindomubaraka.com, not noreply), and the password is the mailbox password, '
                .'not the cPanel password.',

            str_contains($error, 'ssl') || str_contains($error, 'tls') || str_contains($error, 'certificate') => 'A TLS problem. Port 465 needs MAIL_SCHEME=smtps; port 587 needs MAIL_SCHEME left empty '
                .'so the connection starts plain and upgrades with STARTTLS. Note that MAIL_ENCRYPTION '
                .'does nothing in Laravel 11 and later.',

            str_contains($error, 'connection') || str_contains($error, 'timed out') || str_contains($error, 'refused') => 'Nothing answered on that host and port. Some networks block outgoing 465 and 587; '
                .'if this works on the server but not from home, that is what it is.',

            str_contains($error, 'sender') || str_contains($error, 'relay') || str_contains($error, '550') => 'The server refused the sender. MAIL_FROM_ADDRESS should be the same mailbox you are '
                .'authenticating as, or one the server accepts on its behalf.',

            default => 'Check the host, port and scheme against the "Set Up Mail Client" page in cPanel for this mailbox.',
        };

        $this->line('  '.$hint);
    }
}
