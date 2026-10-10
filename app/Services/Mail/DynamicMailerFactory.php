<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class DynamicMailerFactory
{
    public static function envelope(EmailInbox $inbox, Email $email): Envelope
    {
        $envelope = Envelope::create($email);
        if ($inbox->provider === 'yahoo' && $inbox->from_address) {
            // Keep the verified alias in From, but return bounces to the Yahoo mailbox.
            $envelope->setSender(new Address($inbox->email_address));
        }

        return $envelope;
    }

    public function transport(EmailInbox $inbox): EsmtpTransport
    {
        $transport = new EsmtpTransport(
            $inbox->smtp_host, (int) $inbox->smtp_port, $inbox->smtp_encryption === 'ssl'
        );
        $transport->setUsername($inbox->smtp_username);
        $transport->setPassword($inbox->smtp_password);
        $transport->setRequireTls(true);
        $transport->getStream()->setTimeout(30);

        return $transport;
    }

    public function make(EmailInbox $inbox): MailerInterface
    {
        return new Mailer($this->transport($inbox));
    }
}
