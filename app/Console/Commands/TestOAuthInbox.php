<?php

namespace App\Console\Commands;

use App\Models\EmailInbox;
use App\Services\Mail\OAuthAwareMailerFactory;
use Illuminate\Console\Command;
use Symfony\Component\Mime\Email;

class TestOAuthInbox extends Command
{
    protected $signature = 'oauth:test {inbox_id} {--to= : Email address to send test email to}';

    protected $description = 'Test sending an email through an OAuth-connected inbox';

    public function handle()
    {
        $inboxId = $this->argument('inbox_id');
        $to = $this->option('to');

        $inbox = EmailInbox::find($inboxId);

        if (!$inbox) {
            $this->error("Inbox not found with ID: {$inboxId}");
            return 1;
        }

        if (!$inbox->isOAuthConnected()) {
            $this->error("Inbox {$inbox->name} is not OAuth-connected.");
            return 1;
        }

        if (!$to) {
            $to = $inbox->email_address;
            $this->line("No recipient specified, using inbox email: {$to}");
        }

        $this->line("Testing OAuth inbox: <comment>{$inbox->name}</comment>");
        $this->line("Provider: <comment>{$inbox->oauth_provider}</comment>");
        $this->line("Email: <comment>{$inbox->email_address}</comment>");
        $this->line("Recipient: <comment>{$to}</comment>");
        $this->newLine();

        try {
            $factory = app(OAuthAwareMailerFactory::class);

            $email = new Email();
            $email->from($inbox->email_address, $inbox->from_name)
                ->to($to)
                ->subject('OAuth Inbox Test Email')
                ->html(
                    '<h2>Test Email from OAuth Inbox</h2>' .
                    '<p>This is a test email sent through ' . ucfirst($inbox->oauth_provider) . ' OAuth.</p>' .
                    '<p>If you received this, your inbox is properly configured!</p>'
                );

            $this->line("Sending test email...");
            $messageId = $factory->send($inbox, $email);

            $this->newLine();
            $this->info("✓ Email sent successfully!");
            $this->line("Message ID: <comment>{$messageId}</comment>");

            return 0;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("✗ Failed to send email: {$e->getMessage()}");
            return 1;
        }
    }
}
