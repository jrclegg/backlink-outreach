<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Symfony\Component\Mime\Email;

class OAuthAwareMailerFactory
{
    protected InboxConnectProvider $provider;
    protected TokenRefreshService $refreshService;

    public function __construct(
        InboxConnectProvider $provider,
        TokenRefreshService $refreshService
    ) {
        $this->provider = $provider;
        $this->refreshService = $refreshService;
    }

    /**
     * Send an email using the appropriate method based on connection type
     */
    public function send(EmailInbox $inbox, Email $email): string
    {
        // Ensure OAuth token is valid if using OAuth
        if ($inbox->isOAuthConnected()) {
            if (!$this->refreshService->ensureTokenValid($inbox)) {
                throw new \Exception('Failed to refresh OAuth token for ' . $inbox->email_address);
            }
            $inbox->refresh();
        }

        // Use API-based sending for OAuth connections
        if ($inbox->oauth_provider === 'gmail') {
            $mailer = new GmailApiMailer($inbox);
            return $mailer->send($email);
        } elseif ($inbox->oauth_provider === 'outlook') {
            $mailer = new OutlookGraphMailer($inbox);
            return $mailer->send($email);
        }

        // Fall back to SMTP for manual connections
        $factory = new DynamicMailerFactory();
        $mailer = $factory->make($inbox);
        $mailer->send($email);

        return 'smtp_sent';
    }

    /**
     * Get a mailer instance for the inbox
     */
    public function make(EmailInbox $inbox)
    {
        if ($inbox->isOAuthConnected()) {
            if (!$this->refreshService->ensureTokenValid($inbox)) {
                throw new \Exception('Failed to refresh OAuth token for ' . $inbox->email_address);
            }
            $inbox->refresh();

            if ($inbox->oauth_provider === 'gmail') {
                return new GmailApiMailer($inbox);
            } elseif ($inbox->oauth_provider === 'outlook') {
                return new OutlookGraphMailer($inbox);
            }
        }

        return new DynamicMailerFactory();
    }
}
