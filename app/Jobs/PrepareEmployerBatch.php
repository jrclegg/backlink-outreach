<?php

namespace App\Jobs;

use App\Models\EmployerBatch;
use App\Services\Outreach\AcademicEmployerLeads;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\EmployerOutreach;
use App\Services\Outreach\PublicPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class PrepareEmployerBatch implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 700;

    public bool $failOnTimeout = true;

    public function __construct(public EmployerBatch $batch)
    {
        $this->onQueue('research');
    }

    public function handle(Assistant $ai, PublicPage $pages, EmployerOutreach $outreach): void
    {
        if (! EmployerBatch::whereKey($this->batch->id)->where('status', 'queued')->update(['status' => 'researching'])) {
            return;
        }
        try {
            $campaign = $this->batch->campaign;
            $project = $campaign->project;
            if (! $campaign->emailInbox || ! $campaign->emailInbox->is_active || ! $project->emailInboxes()->whereKey($campaign->email_inbox_id)->exists()) {
                throw new RuntimeException('Select an active project email account before preparing outreach.');
            }
            if (config('outreach.academic_jobs_database') || config('outreach.academic_jobs_feed')) {
                app(AcademicEmployerLeads::class)->import($campaign);
            }
            $leadQuery = $campaign->leads()->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()->addDays(EmployerOutreach::MIN_DAYS_TO_DEADLINE)))->where('first_seen', '>=', now()->subDays(60))->orderByDesc('priority')->orderByDesc('first_seen');
            $leadColumns = ['employer', 'job_title', 'job_url', 'source', 'signal', 'country', 'deadline', 'rate_url'];
            $leads = (clone $leadQuery)->limit(20)->get($leadColumns);
            if ($campaign->discover && $campaign->weekly_limit > 8) {
                $groups = (clone $leadQuery)->limit(200)->get($leadColumns)->unique('employer')->values()->chunk(8)->map(fn ($group) => ['leads' => $group->values()->all(), 'focus' => 'Current scraped academic hiring leads'])->all();
                foreach (['UK ecology university departments', 'UK conservation and biodiversity research institutes', 'US ecology and evolution university departments', 'Canadian ecology and conservation researchers', 'Australian ecology university departments', 'European ecology research institutes', 'Scandinavian biodiversity university departments', 'New Zealand ecology university departments'] as $focus) {
                    $groups[] = ['leads' => [], 'focus' => $focus];
                }
                $generation = (string) Str::uuid();
                Cache::put('employer-research-remaining-'.$this->batch->id.'-'.$generation, count($groups), 86400);
                foreach ($groups as $part => $group) {
                    ResearchEmployerContacts::dispatch($this->batch, $part, $group['leads'], $group['focus'], $generation);
                }

                return;
            }
            if ($campaign->discover) {
                $response = $ai->request($project->organization, [['role' => 'user', 'content' => 'Research up to 8 academic employers matching this audience: '.$campaign->audience.'. Find recruitment, department administration or hiring contacts suitable for an employer job-board advertising introduction. Search the live web. Cite official employer pages with published contact emails and evidence of relevant ecology hiring. Require a specific current job advert URL for every contact and cite it in sources. Staff profiles, generic careers pages and broad jobs lists do not count. Omit contacts without a job advert. Do not guess email addresses or treat job applicants as prospects. Exclude these known addresses: '.json_encode($campaign->contacts()->pluck('email')).'. Prioritise these scraped hiring leads, especially paid advertising channels; this signals a budget but does not prove the academic personally paid. Find the hiring or advertising decision maker, not merely an application submission address. Leads: '.json_encode($leads).'. Today: '.now()->toDateString()]], [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 8]]);
                if (($response['stop_reason'] ?? '') !== 'end_turn') {
                    throw new RuntimeException('Employer research did not finish. Narrow the audience and retry.');
                }
                $sources = [];
                foreach ($response['content'] as $block) {
                    foreach ($block['citations'] ?? [] as $source) {
                        if (PublicPage::validUrl($source['url'] ?? '')) {
                            $sources[$source['url']] = $source;
                        }
                    }
                    if (($block['type'] ?? '') === 'web_search_tool_result') {
                        if (isset($block['content']['error_code'])) {
                            throw new RuntimeException('Live employer search failed.');
                        }
                        foreach ($block['content'] as $source) {
                            if (PublicPage::validUrl($source['url'] ?? '')) {
                                $sources[$source['url']] = $source;
                            }
                        }
                    }
                }
                if (! $sources) {
                    throw new RuntimeException('Employer search returned no live sources. No guessed contacts were saved.');
                }
                $report = $ai->text($response);
                $this->batch->update(['research' => $report, 'sources' => array_values($sources)]);
                $data = $ai->json($project->organization, 'Extract JSON {"contacts":[{"employer":"name","email":"published email","source_url":"official contact page in sources","job_advert_url":"specific vacancy URL in sources","job_advert_title":"vacancy title","relevance":"evidence of audience fit and hiring"}]}. Max 8. No invented contacts. Data: '.json_encode(['report' => $report, 'sources' => array_values($sources)]));
                foreach (array_slice($data['contacts'] ?? [], 0, 8) as $candidate) {
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
                    $campaign->contacts()->firstOrCreate(['email' => $email], ['employer' => mb_substr($candidate['employer'], 0, 255), 'source_url' => $url, 'job_advert_url' => $advert, 'job_advert_title' => mb_substr($candidate['job_advert_title'], 0, 255), 'relevance' => mb_substr($candidate['relevance'], 0, 5000), 'verified_at' => now()]);
                }
            }
            $selected = [];
            foreach ($campaign->contacts()->where('status', '!=', 'excluded')->whereDoesntHave('item')->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")->orderBy('id')->get() as $contact) {
                if (! $outreach->eligible($contact)) {
                    continue;
                }
                $item = $this->batch->items()->firstOrCreate(['employer_contact_id' => $contact->id]);
                $selected[] = $item;
                if (count($selected) >= $campaign->weekly_limit) {
                    break;
                }
            }
            $this->batch->update(['status' => $selected ? 'drafting' : 'needs_contacts', 'error' => null]);
            foreach ($selected as $item) {
                DraftEmployerEmail::dispatch($item);
            }
        } catch (\Throwable $e) {
            $this->batch->update(['status' => 'failed', 'error' => $e instanceof RuntimeException ? mb_substr($e->getMessage(), 0, 500) : 'Weekly preparation failed. Check the AI connection and research worker.']);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $this->batch->update(['status' => 'failed', 'error' => 'Employer research worker stopped or timed out.']);
    }
}
