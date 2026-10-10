<?php

namespace App\Jobs;

use App\Models\Opportunity;
use App\Models\ResearchRun;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\PublicPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResearchTargetPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public int $timeout = 700;

    public function __construct(public ResearchRun $run)
    {
        $this->onQueue('research');
    }

    public function handle(Assistant $ai, PublicPage $pages): void
    {
        if (! ResearchRun::whereKey($this->run->id)->where('status', 'queued')->update(['status' => 'running'])) {
            return;
        }
        try {
            $page = $this->run->targetPage;
            $project = $page->project;
            $context = $pages->text($page->url);
            $prompt = 'Research up to 8 high-quality, realistic backlink opportunities for this specific page. Search the live web. Prioritize topical and audience fit and a concrete feasible placement route (curated resource list, directory submission, local partnership, useful editorial addition). Avoid generic guest-post lists and unrealistic major publications. For each give an exact placement URL, relevance, evidence of attainability, contact route (email only if explicitly published), an honest outreach angle, and uncertainty/costs. Cite evidence. Fewer strong results are better than padding. Today: '.now()->toDateString()."\n".json_encode([
                'project' => $project->name, 'website' => $project->website_url, 'project_context' => $project->notes,
                'target_url' => $page->url, 'title' => $page->title, 'brief' => $page->brief,
                'page_content' => $context, 'preferences' => $this->run->instructions,
            ]);
            $messages = [['role' => 'user', 'content' => $prompt]];
            $sources = [];
            $report = '';
            for ($turn = 0; $turn < 3; $turn++) {
                $response = $ai->request($project->organization, $messages, [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 8]]);
                foreach ($response['content'] as $block) {
                    foreach ($block['citations'] ?? [] as $citation) {
                        if (PublicPage::validUrl($citation['url'] ?? '')) {
                            $sources[$citation['url']] = ['url' => $citation['url'], 'title' => $citation['title'] ?? '', 'quote' => $citation['cited_text'] ?? ''];
                        }
                    }
                    if (($block['type'] ?? '') === 'web_search_tool_result') {
                        if (isset($block['content']['error_code'])) {
                            throw new RuntimeException('Web search failed. Check search access and try again.');
                        }
                        foreach ($block['content'] as $source) {
                            if (PublicPage::validUrl($source['url'] ?? '')) {
                                $sources[$source['url']] ??= ['url' => $source['url'], 'title' => $source['title'] ?? '', 'quote' => ''];
                            }
                        }
                    }
                }
                $report .= $ai->text($response)."\n";
                if (($response['stop_reason'] ?? '') !== 'pause_turn') {
                    break;
                }
                $messages[] = ['role' => 'assistant', 'content' => $response['content']];
            }
            if (($response['stop_reason'] ?? '') !== 'end_turn') {
                throw new RuntimeException('Research did not finish. Try a narrower brief.');
            }
            if (! $sources) {
                throw new RuntimeException('Research returned no live sources. No unverified opportunities were saved.');
            }
            $this->run->update(['report' => $report, 'sources' => array_values($sources)]);
            $data = $ai->json($project->organization, 'Convert this research into JSON only: {"opportunities":[{"url":"exact placement page","title":"name","type":"directory/resource/partnership/editorial/guest_post","relevance":"reason","attainability":"evidence and uncertainties","angle":"honest pitch","contact_email":null,"contact_url":null,"source_urls":["evidence URL"]}]}. Only use URLs in the provided sources. Do not invent emails. Max 8. If there are no credible opportunities return an empty list. Research: '.json_encode(['report' => $report, 'sources' => array_values($sources)]));
            if (! isset($data['opportunities']) || ! is_array($data['opportunities'])) {
                throw new RuntimeException('Research format was invalid.');
            }
            DB::transaction(function () use ($data, $sources, $page) {
                foreach (array_slice($data['opportunities'], 0, 8) as $item) {
                    if (! is_array($item) || ! isset($sources[$item['url'] ?? ''])) {
                        continue;
                    }
                    $evidence = array_values(array_intersect_key($sources, array_flip(array_filter($item['source_urls'] ?? [], 'is_string'))));
                    if (! $evidence || empty($item['relevance']) || empty($item['attainability']) || empty($item['angle'])) {
                        continue;
                    }
                    Opportunity::firstOrCreate(['target_page_id' => $page->id, 'url' => $item['url']], [
                        'research_run_id' => $this->run->id, 'title' => mb_substr($item['title'] ?? $item['url'], 0, 255),
                        'type' => mb_substr($item['type'] ?? 'resource', 0, 255), 'relevance' => $item['relevance'],
                        'attainability' => $item['attainability'], 'angle' => $item['angle'], 'sources' => $evidence,
                        // Contacts remain reviewable suggestions, never an automatic send destination.
                        'contact_email' => filter_var($item['contact_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                        'contact_url' => isset($sources[$item['contact_url'] ?? '']) ? $item['contact_url'] : null,
                    ]);
                }
                $this->run->update(['status' => 'completed', 'error' => null]);
            });
        } catch (\Throwable $e) {
            $this->run->update(['status' => 'failed', 'error' => $e instanceof RuntimeException ? mb_substr($e->getMessage(), 0, 500) : 'Research failed. Check the queue and AI configuration, then retry.']);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $this->run->update(['status' => 'failed', 'error' => 'Research worker stopped or timed out. Please retry.']);
    }
}
