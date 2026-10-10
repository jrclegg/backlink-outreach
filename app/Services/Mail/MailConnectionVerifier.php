<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Illuminate\Validation\ValidationException;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class MailConnectionVerifier
{
    public function verify(EmailInbox $inbox): void
    {
        $transport = null;
        $client = null;
        $stage = 'sending';
        $checkingFolder = false;
        try {
            $transport = app(DynamicMailerFactory::class)->transport($inbox);
            $transport->start();
            $transport->stop();
            $transport = null;
            $stage = 'receiving';
            $client = app(ImapGateway::class)->client($inbox);
            $client->connect();
            $checkingFolder = true;
            $path = $inbox->imap_folder ?: 'INBOX';
            if (strcasecmp($path, 'INBOX') === 0) {
                $client->openFolder('INBOX');
            } elseif (! $client->getFolderByPath($path)) {
                throw new \RuntimeException('Missing incoming folder');
            }
        } catch (\Throwable $e) {
            $help = ProviderSettings::help($inbox->provider);
            $detail = $help['failure'];
            if ($stage === 'receiving') {
                $detail = match (true) {
                    $e instanceof AuthFailedException => 'The incoming server rejected the mailbox login. Check your full mailbox address and app password.',
                    $e instanceof ConnectionFailedException => 'The app could not establish a secure connection to the incoming server. This can be a network, server, or certificate problem.',
                    $checkingFolder => 'The incoming login succeeded, but the app could not find or read the configured incoming folder. Check the folder setting. Diagnostic type: '.class_basename($e).'.',
                    default => 'The incoming connection failed before folder checking. Diagnostic type: '.class_basename($e).'.',
                };
            }
            throw ValidationException::withMessages(['connection' => "We couldn’t connect for {$stage}. {$detail} No account changes were saved."]);
        } finally {
            try {
                $transport?->stop();
            } catch (\Throwable) {
            }
            try {
                $client?->disconnect();
            } catch (\Throwable) {
            }
        }
    }
}
