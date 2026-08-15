<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;

class OutlookGraphMailer
{
    private EmailInbox $inbox;
    private string $accessToken;

    public function __construct(EmailInbox $inbox)
    {
        if ($inbox->oauth_provider !== 'outlook') {
            throw new \Exception('Inbox is not connected via Outlook OAuth');
        }

        $this->inbox = $inbox;
        $this->accessToken = $inbox->oauth_access_token;
    }

    public function send(Email $email): string
    {
        try {
            $payload = $this->createMessagePayload($email);

            $response = Http::withToken($this->accessToken)
                ->post('https://graph.microsoft.com/v1.0/me/sendMail', [
                    'message' => $payload,
                    'saveToSentItems' => true,
                ]);

            if (!$response->successful()) {
                throw new \Exception('Microsoft Graph API error: ' . $response->body());
            }

            // Microsoft doesn't return a message ID in the send response,
            // so we generate one
            return 'outlook_' . uniqid();
        } catch (\Exception $e) {
            throw new \Exception('Failed to send email via Outlook API: ' . $e->getMessage());
        }
    }

    private function createMessagePayload(Email $email): array
    {
        $payload = [
            'subject' => $email->getSubject(),
            'body' => [
                'contentType' => $email->getHtmlBody() ? 'HTML' : 'text',
                'content' => $email->getHtmlBody() ?? (string) $email->getTextBody(),
            ],
            'toRecipients' => [],
        ];

        // Add to recipients
        if ($email->getTo()) {
            foreach ($email->getTo() as $email_address => $display_name) {
                $payload['toRecipients'][] = [
                    'emailAddress' => [
                        'address' => $email_address,
                        'name' => $display_name,
                    ],
                ];
            }
        }

        // Add from
        if ($email->getFrom()) {
            foreach ($email->getFrom() as $email_address => $display_name) {
                $payload['from'] = [
                    'emailAddress' => [
                        'address' => $email_address,
                        'name' => $display_name,
                    ],
                ];
            }
        }

        // Add cc recipients
        if ($email->getCc()) {
            $payload['ccRecipients'] = [];
            foreach ($email->getCc() as $email_address => $display_name) {
                $payload['ccRecipients'][] = [
                    'emailAddress' => [
                        'address' => $email_address,
                        'name' => $display_name,
                    ],
                ];
            }
        }

        // Add bcc recipients
        if ($email->getBcc()) {
            $payload['bccRecipients'] = [];
            foreach ($email->getBcc() as $email_address => $display_name) {
                $payload['bccRecipients'][] = [
                    'emailAddress' => [
                        'address' => $email_address,
                        'name' => $display_name,
                    ],
                ];
            }
        }

        // Add reply-to
        if ($email->getReplyTo()) {
            $payload['replyTo'] = [];
            foreach ($email->getReplyTo() as $email_address => $display_name) {
                $payload['replyTo'][] = [
                    'emailAddress' => [
                        'address' => $email_address,
                        'name' => $display_name,
                    ],
                ];
            }
        }

        return $payload;
    }
}
