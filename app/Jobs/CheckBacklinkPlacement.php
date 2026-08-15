<?php

namespace App\Jobs;

use App\Models\BacklinkCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CheckBacklinkPlacement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public BacklinkCheck $check)
    {}

    public function handle(): void
    {
        $check = $this->check->fresh();

        try {
            $pageContent = Http::timeout(10)->get($check->target_page_url)->body();

            // Search for the expected link
            $found = false;

            if ($check->expected_anchor_text) {
                // Look for specific anchor text
                $pattern = sprintf(
                    '/<a[^>]+href=["\']([^"\']*%s[^"\']*)["\'][^>]*>%s<\/a>/i',
                    preg_quote($check->expected_link_url),
                    preg_quote($check->expected_anchor_text)
                );
                $found = preg_match($pattern, $pageContent) > 0;
            } else {
                // Just look for the URL
                $found = str_contains($pageContent, $check->expected_link_url);
            }

            if ($found) {
                if ($check->status !== 'found') {
                    $check->update([
                        'status' => 'found',
                        'found_at' => now(),
                    ]);
                }
            } else {
                if ($check->status === 'found') {
                    // Link was placed but is now missing
                    $check->update([
                        'status' => 'lost',
                        'lost_at' => now(),
                    ]);
                } else {
                    $check->update(['status' => 'not_found']);
                }
            }

            // Schedule next check
            $daysUntilNext = match ($check->status) {
                'found' => 7, // Weekly if found
                'not_found' => 3, // Check again in 3 days
                'lost' => 1, // Check daily if lost
                default => 7,
            };

            $check->update([
                'last_checked_at' => now(),
                'next_check_at' => now()->addDays($daysUntilNext),
                'check_count' => $check->check_count + 1,
            ]);
        } catch (\Exception $e) {
            $check->update([
                'status' => 'error',
                'last_checked_at' => now(),
                'next_check_at' => now()->addHours(1),
            ]);
        }
    }
}
