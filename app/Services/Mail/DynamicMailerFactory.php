<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

class DynamicMailerFactory
{
    public function make(EmailInbox $inbox): Mailer
    {
        // For OAuth connections, we need to ensure the token is valid
        if ($inbox->isOAuthConnected()) {
            // Ensure token is refreshed if needed
            $tokenRefreshService = app(TokenRefreshService::class);
            if (!$tokenRefreshService->ensureTokenValid($inbox)) {
                throw new \Exception('Failed to refresh OAuth token for ' . $inbox->email_address);
            }
            $inbox->refresh();
        }

        $scheme = $inbox->smtp_encryption === 'ssl' ? 'smtps' : 'smtp';
        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            rawurlencode($inbox->smtp_username),
            rawurlencode($inbox->smtp_password),
            $inbox->smtp_host,
            $inbox->smtp_port
        );

        return new Mailer(Transport::fromDsn($dsn));
    }
}
