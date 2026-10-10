<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;

class ImapGateway
{
    public function client(EmailInbox $inbox)
    {
        return (new ClientManager)->make([
            'host' => $inbox->imap_host, 'port' => $inbox->imap_port, 'encryption' => $inbox->imap_encryption,
            'validate_cert' => true, 'username' => $inbox->imap_username, 'password' => $inbox->imap_password,
            'protocol' => 'imap', 'timeout' => 20,
        ]);
    }

    public function messages(EmailInbox $inbox): \Generator
    {
        $client = $this->client($inbox);
        try {
            $client->connect();
            $path = $inbox->imap_folder ?: 'INBOX';
            if (strcasecmp($path, 'INBOX') === 0) {
                // INBOX is a standard IMAP mailbox; it does not require LIST discovery.
                $client->openFolder('INBOX');
                $folder = new Folder($client, 'INBOX', '/', []);
            } else {
                $folder = $client->getFolderByPath($path);
            }
            if (! $folder) {
                throw new \RuntimeException('IMAP folder not found.');
            }
            $since = $inbox->last_polled_at?->copy()->subDay() ?? now()->subDays(30);
            for ($page = 1; ; $page++) {
                $messages = $folder->query()->since($since)->leaveUnread()->fetchOrderAsc()->limit(10, $page)->get();
                foreach ($messages as $message) {
                    $from = $message->getFrom()->first();
                    $to = $message->getTo()->first();
                    yield [
                        'message_id' => trim((string) $message->getMessageId(), "<> \t\r\n"),
                        'in_reply_to' => (string) $message->getInReplyTo(),
                        'references' => (string) $message->getReferences(),
                        'from' => strtolower($from?->mail ?? ''), 'to' => strtolower($to?->mail ?? $inbox->email_address),
                        'subject' => mb_substr((string) $message->getSubject(), 0, 255),
                        'body' => mb_substr($message->getTextBody() ?: html_entity_decode(strip_tags($message->getHTMLBody())), 0, 200000),
                        'received_at' => $message->getDate()->first() ?: now(),
                    ];
                }
                if ($messages->count() < 10) {
                    break;
                }
            }
        } finally {
            $client->disconnect();
        }
    }
}
