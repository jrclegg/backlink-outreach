<?php

namespace App\Jobs;

use App\Models\EmployerBatch;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\EmployerOutreach;
use App\Services\Outreach\PublicPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResearchEmployerContacts implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 700;

    public bool $failOnTimeout = true;

    public string $generation = '';

    public function __construct(public EmployerBatch $batch, public int $part, public array $leads, public string $focus = '', string $generation = '')
    {
        $this->generation = $generation;
        $this->onQueue('employer-research');
    }

    public function handle(Assistant $ai, PublicPage $pages): void
    {
        if (! Cache::add('employer-research-started-'.$this->batch->id.($this->generation ? '-'.$this->generation : '').'-'.$this->part, true, 86400)) {
            return;
        }
        try {
            $c = $this->batch->campaign;
            $response = $ai->request($c->project->organization, [['role' => 'user', 'content' => 'Search live official employer websites and research up to 8 NEW suitable hiring contacts for ecology/evolution/conservation academic employer outreach. Audience: '.$c->audience.'. Focus: '.$this->focus.'. Prioritise these current hiring leads: '.json_encode($this->leads).'. If a lead has no suitable public contact, find another relevant employer. One contact per employer where possible. Hiring PIs and department administrators are acceptable, but flag unknown purchasing authority. Only include employers with a specific current job advert URL. Staff profiles, generic careers pages and broad jobs lists do not count as job adverts. Include the advert URL in sources. Omit contacts without a job advert; never invent a URL. Do not guess addresses. A published email must appear literally on an official source page you open. Return JSON only {"contacts":[{"employer":"name","email":"published address","source_url":"official source URL","job_advert_url":"specific vacancy URL in sources","job_advert_title":"vacancy title","relevance":"specific relevant research/hiring evidence; note uncertain purchasing authority"}]}. Cite every source URL in sources. Avoid these already collected addresses: '.json_encode($c->contacts()->pluck('email')).'. Do not send anything. Today: '.now()->toDateString()]], [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 8]]);
            if (($response['stop_reason'] ?? '') !== 'end_turn') {
                throw new RuntimeException('Research did not finish.');
            }
            $sources = [];
            foreach ($response['content'] as $block) {
                foreach ($block['citations'] ?? [] as $source) {
                    if (PublicPage::validUrl($source['url'] ?? '')) {
                        $sources[$source['url']] = $source;
                    }
                }
                if (($block['type'] ?? '') === 'web_search_tool_result') {
                    foreach ($block['content'] ?? [] as $source) {
                        if (is_array($source) && PublicPage::validUrl($source['url'] ?? '')) {
                            $sources[$source['url']] = $source;
                        }
                    }
                }
            }
            $text = trim($ai->text($response));
            $data = json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text), true);
            if (! is_array($data['contacts'] ?? null) || ! $sources) {
                throw new RuntimeException('Research returned no usable source-grounded contacts.');
            }
            $saved = 0;
            foreach (array_slice($data['contacts'], 0, 8) as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }
                $email = strtolower(trim($candidate['email'] ?? ''));
                $url = $candidate['source_url'] ?? '';
                $advert = $candidate['job_advert_url'] ?? '';
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! isset($sources[$url]) || ! PublicPage::validUrl($advert) || ! isset($sources[$advert]) || empty($candidate['job_advert_title']) || empty($candidate['employer']) || empty($candidate['relevance'])) {
                    continue;
                }
                try {
                    $html = html_entity_decode($pages->fetch($url));
                } catch (\Throwable) {
                    continue;
                }
                preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $html, $matches);
                if (! in_array($email, array_map('strtolower', $matches[0]))) {
                    continue;
                }
                $contact = $c->contacts()->firstOrCreate(['email' => $email], ['employer' => mb_substr($candidate['employer'], 0, 255), 'source_url' => $url, 'job_advert_url' => $advert, 'job_advert_title' => mb_substr($candidate['job_advert_title'], 0, 255), 'relevance' => mb_substr($candidate['relevance'], 0, 5000), 'verified_at' => now()]);
                if ($contact->wasRecentlyCreated) {
                    $saved++;
                }
            }
            DB::transaction(function () use ($text, $sources, $saved) {
                $b = EmployerBatch::whereKey($this->batch->id)->lockForUpdate()->firstOrFail();
                $b->update(['research' => ($b->research ?? '')."\n\nResearch part ".$this->part.': '.$saved." verified new contacts.\n".$text, 'sources' => array_values(array_merge($b->sources ?? [], array_values($sources)))]);
            });
        } catch (\Throwable $e) {
            DB::transaction(function () use ($e) {
                $b = EmployerBatch::whereKey($this->batch->id)->lockForUpdate()->firstOrFail();
                $b->update(['research' => ($b->research ?? '')."\nResearch part ".$this->part.' failed: '.($e instanceof RuntimeException ? $e->getMessage() : 'Source verification failed.')]);
            });
        } finally {
            $this->finish();
        }
    }

    public function finish(): void
    {
        if (! Cache::add('employer-research-finished-'.$this->batch->id.($this->generation ? '-'.$this->generation : '').'-'.$this->part, true, 86400)) {
            return;
        }
        if (Cache::decrement('employer-research-remaining-'.$this->batch->id.($this->generation ? '-'.$this->generation : '')) > 0) {
            return;
        }
        $b = $this->batch->fresh();
        $c = $b->campaign;
        $remaining = max(0, $c->weekly_limit - $b->items()->count());
        foreach ($c->contacts()->where('status', '!=', 'excluded')->whereDoesntHave('item')->orderBy('id')->get() as $contact) {
            if (! $remaining) {
                break;
            }if (! app(EmployerOutreach::class)->eligible($contact)) {
                continue;
            }$item = $b->items()->create(['employer_contact_id' => $contact->id]);
            DraftEmployerEmail::dispatch($item);
            $remaining--;
        }
        $b->update(['status' => $b->items()->where('status', 'queued')->exists() ? 'drafting' : ($b->items()->exists() ? 'ready' : 'needs_contacts'), 'error' => null]);
    }

    public function failed(?\Throwable $e): void
    {
        $this->finish();
    }
}
