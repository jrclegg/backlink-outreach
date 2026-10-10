<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\CodexAssistant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class CodexAssistantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['outreach.ai_provider' => 'codex', 'outreach.codex_binary' => 'codex', 'outreach.codex_model' => '']);
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
    }

    private function codexOutput(string $text, array $sources = [], bool $searched = false): string
    {
        return ($searched ? json_encode(['type' => 'item.completed', 'item' => ['type' => 'web_search']])."\n" : '')
            .json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => json_encode(compact('text', 'sources'))]])."\n"
            .json_encode(['type' => 'turn.completed']);
    }

    public function test_drafting_uses_codex_without_an_api_key_and_disables_search_and_shell(): void
    {
        Process::fake(['*login*status*' => Process::result(errorOutput: 'Logged in using ChatGPT'), '*' => Process::result(output: $this->codexOutput('{"subject":"Hello","body":"A useful resource"}'))]);
        $draft = (new Assistant)->json(new Organization, 'Write a draft.');
        $this->assertSame('Hello', $draft['subject']);
        Process::assertRan(function ($process) {
            if (($process->command[1] ?? '') === 'login') {
                return false;
            }
            $this->assertContains('web_search="disabled"', $process->command);
            $this->assertContains('features.shell_tool=false', $process->command);
            $this->assertContains('--ignore-user-config', $process->command);
            $this->assertContains('read-only', $process->command);
            $this->assertFalse($process->environment['CODEX_API_KEY']);
            $this->assertFalse($process->environment['OPENAI_API_KEY']);
            $this->assertDirectoryDoesNotExist($process->path);

            return true;
        });
        Http::assertNothingSent();
    }

    public function test_research_returns_citations_and_uses_live_search(): void
    {
        $source = ['url' => 'https://example.org/resources', 'title' => 'Resources', 'cited_text' => 'Submit a resource'];
        Process::fake(['*login*status*' => Process::result(errorOutput: 'Logged in using ChatGPT'), '*' => Process::result(output: $this->codexOutput('Relevant directory', [$source], true))]);
        $result = (new Assistant)->request(new Organization, [], [['name' => 'web_search']]);
        $this->assertSame([$source], $result['content'][0]['citations']);
        Process::assertRan(fn ($process) => in_array('web_search="live"', $process->command));
    }

    public function test_research_rejects_sources_when_no_search_occurred(): void
    {
        Process::fake(['*login*status*' => Process::result(errorOutput: 'Logged in using ChatGPT'), '*' => Process::result(output: $this->codexOutput('Invented research'))]);
        $this->expectExceptionMessage('Codex did not search the live web');
        (new CodexAssistant)->request([], true);
    }

    public function test_failed_process_does_not_expose_raw_output(): void
    {
        Process::fake(['*login*status*' => Process::result(errorOutput: 'Logged in using ChatGPT'), '*' => Process::result(errorOutput: 'private credentials', exitCode: 1)]);
        try {
            (new CodexAssistant)->request([], false);
            $this->fail('Expected an error');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Codex could not complete', $e->getMessage());
            $this->assertStringNotContainsString('private credentials', $e->getMessage());
        }
        Process::assertRan(function ($process) {
            if (($process->command[1] ?? '') === 'login') {
                return false;
            }
            $this->assertDirectoryDoesNotExist($process->path);

            return true;
        });
    }

    public function test_failures_explain_the_cause_without_exposing_raw_diagnostics(): void
    {
        foreach ([
            'You have hit your usage limit' => 'Codex usage or rate limit reached',
            'Authentication failed: refresh token expired' => 'Codex authentication failed',
            'error sending request: connection reset' => 'Codex connection failed',
            'The model is not supported' => 'configured Codex model is unavailable',
        ] as $diagnostic => $expected) {
            Process::fake(['*login*status*' => Process::result(errorOutput: 'Logged in using ChatGPT'), '*' => Process::result(errorOutput: $diagnostic.' private credentials', exitCode: 1)]);
            try {
                (new CodexAssistant)->request([], false);
                $this->fail('Expected an error');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
                $this->assertStringNotContainsString('private credentials', $e->getMessage());
            }
        }
    }

    public function test_json_failure_events_use_the_same_safe_diagnostics(): void
    {
        Process::fake(['*login*status*' => Process::result(errorOutput: 'Logged in using ChatGPT'), '*' => Process::result(output: json_encode(['type' => 'turn.failed', 'error' => ['message' => 'Usage limit reached: private credentials']]))]);
        $this->expectExceptionMessage('Codex usage or rate limit reached');
        (new CodexAssistant)->request([], false);
    }

    public function test_invalid_response_is_rejected(): void
    {
        Process::fake(['*login*status*' => Process::result(errorOutput: 'Logged in using ChatGPT'), '*' => Process::result(output: 'not json')]);
        $this->expectExceptionMessage('Codex returned an incomplete response');
        (new CodexAssistant)->request([], false);
    }

    public function test_connection_requires_chatgpt_login(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push(Process::result(errorOutput: 'Logged in using ChatGPT'))
            ->push(Process::result(errorOutput: 'Logged in using an API key'))
            ->push(Process::result(errorOutput: 'Not logged in', exitCode: 1))]);
        $this->assertTrue((new CodexAssistant)->status());
        $this->assertFalse((new CodexAssistant)->status());
        $this->assertFalse((new CodexAssistant)->status());
    }
}
