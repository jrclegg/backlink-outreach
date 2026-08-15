<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

class DynamicMailerFactory
{
    public function make(EmailInbox $inbox): Mailer
    {
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
