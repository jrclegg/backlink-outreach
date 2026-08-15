<?php

namespace App\Console\Commands;

use App\Models\EmailInbox;
use App\Services\Mail\InboxConnectProvider;
use Illuminate\Console\Command;

class RefreshOAuthTokens extends Command
{
    protected $signature = 'oauth:refresh-tokens {--dry-run}';

    protected $description = 'Refresh OAuth tokens that are expiring soon';

    public function handle()
    {
        $provider = app(InboxConnectProvider::class);
        $dryRun = $this->option('dry-run');

        // Find all OAuth inboxes with tokens expiring within 1 hour
        $inboxes = EmailInbox::query()
            ->whereNotNull('oauth_provider')
            ->whereNotNull('oauth_refresh_token')
            ->where(function ($query) {
                $query->where('oauth_expires_at', '<', now()->addHour())
                    ->orWhere('oauth_expires_at', null);
            })
            ->get();

        if ($inboxes->isEmpty()) {
            $this->info('No OAuth tokens need refreshing.');
            return 0;
        }

        $this->info("Found {$inboxes->count()} OAuth inboxes to refresh.");

        $refreshed = 0;
        $failed = 0;

        foreach ($inboxes as $inbox) {
            $this->line("Refreshing token for {$inbox->email_address} ({$inbox->oauth_provider})...");

            if ($dryRun) {
                $this->line("  [DRY RUN] Would refresh token");
                continue;
            }

            try {
                if ($inbox->oauth_provider === 'gmail') {
                    $success = $provider->refreshGoogleToken($inbox);
                } elseif ($inbox->oauth_provider === 'outlook') {
                    $success = $provider->refreshOutlookToken($inbox);
                } else {
                    $this->error("  Unknown provider: {$inbox->oauth_provider}");
                    $failed++;
                    continue;
                }

                if ($success) {
                    $this->line("  <info>✓ Token refreshed successfully</info>");
                    $refreshed++;
                } else {
                    $this->line("  <error>✗ Failed to refresh token</error>");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  Error: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Refresh complete: {$refreshed} succeeded, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }
}
