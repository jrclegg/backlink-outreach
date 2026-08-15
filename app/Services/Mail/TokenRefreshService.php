<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;

class TokenRefreshService
{
    protected InboxConnectProvider $provider;

    public function __construct(InboxConnectProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Ensure token is valid before operations that need it.
     * Refreshes if expired, returns false if refresh fails.
     */
    public function ensureTokenValid(EmailInbox $inbox): bool
    {
        return $this->provider->ensureTokenValid($inbox);
    }

    /**
     * Get a mailer instance with refreshed token if needed.
     */
    public function getMailerForInbox(EmailInbox $inbox): DynamicMailerFactory
    {
        // Ensure token is valid before creating mailer
        if ($inbox->isOAuthConnected()) {
            if (!$this->ensureTokenValid($inbox)) {
                throw new \Exception('Failed to refresh OAuth token for ' . $inbox->email_address);
            }

            // Refresh the model instance to get latest token
            $inbox->refresh();
        }

        $factory = new DynamicMailerFactory();
        return $factory;
    }

    /**
     * Check if we need to refresh a token soon.
     */
    public function shouldRefreshSoon(EmailInbox $inbox, int $minutesBefore = 15): bool
    {
        if (!$inbox->oauth_expires_at) {
            return false;
        }

        $refreshTime = $inbox->oauth_expires_at->subMinutes($minutesBefore);
        return now()->isAfter($refreshTime);
    }
}
