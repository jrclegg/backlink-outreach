<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Google_Client;
use Google_Service_Gmail;
use Symfony\Component\Mime\Email;
use Swift_Message;

class GmailApiMailer
{
    private Google_Client $client;
    private Google_Service_Gmail $service;
    private EmailInbox $inbox;

    public function __construct(EmailInbox $inbox)
    {
        if ($inbox->oauth_provider !== 'gmail') {
            throw new \Exception('Inbox is not connected via Gmail OAuth');
        }

        $this->inbox = $inbox;
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        $this->client = new Google_Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));

        $token = [
            'access_token' => $this->inbox->oauth_access_token,
            'refresh_token' => $this->inbox->oauth_refresh_token,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];

        $this->client->setAccessToken($token);

        $this->service = new Google_Service_Gmail($this->client);
    }

    public function send(Email $email): string
    {
        try {
            $message = $this->createMessage($email);
            $result = $this->service->users_messages->send('me', $message);
            return $result->getId();
        } catch (\Exception $e) {
            throw new \Exception('Failed to send email via Gmail API: ' . $e->getMessage());
        }
    }

    private function createMessage(Email $email): \Google_Service_Gmail_Message
    {
        $swiftMessage = new Swift_Message();
        $swiftMessage->setFrom($email->getFrom())
            ->setTo($email->getTo())
            ->setSubject($email->getSubject())
            ->setBody((string) $email->getTextBody(), 'text/plain');

        if ($email->getHtmlBody()) {
            $swiftMessage->addPart((string) $email->getHtmlBody(), 'text/html');
        }

        // Add reply-to and cc/bcc if present
        if ($replyTo = $email->getReplyTo()) {
            $swiftMessage->setReplyTo(array_keys($replyTo));
        }

        if ($cc = $email->getCc()) {
            $swiftMessage->setCc(array_keys($cc));
        }

        if ($bcc = $email->getBcc()) {
            $swiftMessage->setBcc(array_keys($bcc));
        }

        $message = new \Google_Service_Gmail_Message();
        $message->setRaw($this->encodeMessage($swiftMessage));

        return $message;
    }

    private function encodeMessage(Swift_Message $message): string
    {
        return rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');
    }
}
