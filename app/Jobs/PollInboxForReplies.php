<?php

namespace App\Jobs;

use App\Models\EmailInbox;
use App\Services\Mail\ImapGateway;
use App\Services\Outreach\Mailbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class PollInboxForReplies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public EmailInbox $inbox) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('imap-'.$this->inbox->id))->releaseAfter(30)->expireAfter(360)];
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ImapGateway $gateway, Mailbox $mailbox): void
    {
        $inbox = $this->inbox->fresh();
        if (! $inbox || ! $inbox->is_active) {
            return;
        }
        $startedAt = now();
        try {
            foreach ($gateway->messages($inbox) as $message) {
                $mailbox->ingest($inbox, $message);
            }
            $inbox->update(['last_polled_at' => $startedAt, 'sync_error' => null]);
        } catch (\Throwable $e) {
            $inbox->update(['sync_error' => 'Sync failed. Check IMAP host, folder, credentials and provider access. Your last successful sync was preserved.']);
            throw new \RuntimeException('Inbox synchronization failed for inbox '.$inbox->id);
        }
    }
}
