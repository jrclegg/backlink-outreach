<?php

namespace App\Jobs;

use App\Models\Opportunity;
use App\Services\Outreach\PublicPage;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\DomCrawler\Crawler;

class CheckOpportunityLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Opportunity $opportunity) {}

    public function handle(PublicPage $pages): void
    {
        $op = $this->opportunity->fresh();
        try {
            $url = $op->placement_url ?: $op->url;
            $crawler = new Crawler($pages->fetch($url), $url);
            $expected = rtrim($op->targetPage->url, '/');
            $found = false;
            foreach ($crawler->filter('a[href]') as $node) {
                $link = (string) UriResolver::resolve(new Uri($url), new Uri($node->getAttribute('href')));
                if (rtrim(explode('#', $link)[0], '/') === $expected) {
                    $found = true;
                    break;
                }
            }
            $status = $found ? 'found' : (in_array($op->link_status, ['found', 'lost']) ? 'lost' : 'not_found');
            $op->update(['link_status' => $status, 'checked_at' => now(), 'next_check_at' => now()->addDays($found ? 7 : 1)] + ($found ? ['status' => 'placed'] : []));
        } catch (\Throwable $e) {
            $op->update(['link_status' => 'error', 'checked_at' => now(), 'next_check_at' => now()->addDay()]);
        }
    }
}
