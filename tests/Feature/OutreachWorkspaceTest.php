<?php

namespace Tests\Feature;

use App\Jobs\CheckOpportunityLink;
use App\Jobs\PollInboxForReplies;
use App\Jobs\ResearchTargetPage;
use App\Jobs\SendReviewedMessage;
use App\Livewire\Inboxes\InboxForm;
use App\Livewire\Workspace\AiSettings;
use App\Livewire\Workspace\ProjectWorkspace;
use App\Livewire\Workspace\UnassignedMail;
use App\Models\Conversation;
use App\Models\EmailInbox;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\OutreachMessage;
use App\Models\Project;
use App\Models\ResearchRun;
use App\Models\Suppression;
use App\Models\TargetPage;
use App\Models\User;
use App\Services\Mail\DynamicMailerFactory;
use App\Services\Mail\ImapGateway;
use App\Services\Mail\MailConnectionVerifier;
use App\Services\Mail\MailProviderDetector;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\CodexAssistant;
use App\Services\Outreach\Mailbox;
use App\Services\Outreach\PublicPage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\Mailer\MailerInterface;
use Tests\TestCase;

class OutreachWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Project $project;

    private EmailInbox $inbox;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        config(['outreach.ai_provider' => 'anthropic']);
        Http::preventStrayRequests();
        Queue::fake();
        $this->org = Organization::create(['name' => 'My sites', 'slug' => 'my-sites', 'llm_api_key' => 'test-secret']);
        $user = User::factory()->create(['current_organization_id' => $this->org->id]);
        $this->actingAs($user);
        $this->project = $this->org->projects()->create(['name' => 'Coworking Space Hub', 'website_url' => 'https://coworkingspacehub.com', 'notes' => 'Useful guides for remote workers']);
        $this->inbox = $this->org->emailInboxes()->create(['name' => 'Outreach', 'email_address' => 'owner@example.com', 'from_name' => 'Owner', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'owner@example.com', 'smtp_password' => 'secret', 'smtp_encryption' => 'tls', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'owner@example.com', 'imap_password' => 'secret', 'imap_encryption' => 'ssl', 'default_project_id' => $this->project->id]);
        $this->project->emailInboxes()->attach($this->inbox);
    }

    public function test_sync_without_an_active_project_account_does_not_claim_to_queue(): void
    {
        $this->project->emailInboxes()->detach();
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])
            ->call('sync')
            ->assertSet('notice', 'No active email account is connected to this project. Connect your mailbox and assign it to this project before syncing.');
        Queue::assertNotPushed(PollInboxForReplies::class);
    }

    private function page(): TargetPage
    {
        return TargetPage::create(['project_id' => $this->project->id, 'url' => 'https://coworkingspacehub.com/cardiff', 'title' => 'Cardiff coworking', 'brief' => 'Comparison of local workspaces']);
    }

    private function thread(?EmailInbox $inbox = null): Conversation
    {
        return Conversation::create(['project_id' => $this->project->id, 'email_inbox_id' => ($inbox ?? $this->inbox)->id, 'contact_email' => 'editor@example.org', 'subject' => 'A useful resource']);
    }

    private function message(Conversation $thread, array $extra = []): OutreachMessage
    {
        return $thread->messages()->create($extra + ['email_inbox_id' => $thread->email_inbox_id, 'direction' => 'outbound', 'status' => 'sent', 'from_email' => 'owner@example.com', 'to_email' => 'editor@example.org', 'subject' => $thread->subject, 'body' => 'A useful resource', 'message_id' => 'original@example.com', 'sent_at' => now()]);
    }

    public function test_workspace_and_settings_render_without_exposing_credentials(): void
    {
        $this->get(route('projects.workspace', $this->project))->assertOk()->assertSee('Coworking Space Hub')->assertDontSee('test-secret');
        $this->get(route('ai.settings'))->assertOk()->assertDontSee('test-secret');
        $this->get(route('inboxes.edit', $this->inbox))->assertOk()->assertDontSee('value="secret"', false);
        $this->get(route('mail.unassigned'))->assertOk();
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])->set('tab', 'mail')->assertSee('Compose an email');
    }

    public function test_add_target_page_and_queue_research_without_sending(): void
    {
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])->set('pageUrl', 'https://coworkingspacehub.com/cardiff')->set('pageTitle', 'Cardiff')->call('addPage')->assertHasNoErrors()->call('research')->assertHasNoErrors();
        $this->assertDatabaseCount('target_pages', 1);
        $this->assertDatabaseCount('research_runs', 1);
        Queue::assertPushed(ResearchTargetPage::class);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_website_pages_can_be_discovered_selected_and_researched(): void
    {
        $reader = Mockery::mock(PublicPage::class);
        $reader->shouldReceive('fetch')->with($this->project->website_url)->once()->andReturn('<title>Coworking Space Hub</title><a href="/cardiff">Cardiff spaces</a><a href="https://other.example/page">Other site</a>');
        $reader->shouldReceive('fetch')->with($this->project->website_url.'/sitemap.xml')->once()->andReturn('<sitemapindex><sitemap><loc>https://coworkingspacehub.com/pages.xml</loc></sitemap></sitemapindex>');
        $reader->shouldReceive('fetch')->with($this->project->website_url.'/pages.xml')->once()->andReturn('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://coworkingspacehub.com/london</loc></url><url><loc>https://other.example/hidden</loc></url></urlset>');
        $this->app->instance(PublicPage::class, $reader);

        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])
            ->call('loadSitePages')->assertSet('pagesLoaded', true)
            ->assertSee('https://coworkingspacehub.com/london')->assertDontSee('https://other.example/hidden')
            ->set('selectedSitePage', 'https://coworkingspacehub.com/cardiff')
            ->assertSet('pageTitle', 'Cardiff spaces')->call('addPage')->assertHasNoErrors()
            ->call('research')->assertHasNoErrors();
        $this->assertDatabaseHas('target_pages', ['url' => 'https://coworkingspacehub.com/cardiff', 'title' => 'Cardiff spaces']);
        Queue::assertPushed(ResearchTargetPage::class);
    }

    public function test_page_discovery_failure_keeps_manual_entry_available(): void
    {
        $reader = Mockery::mock(PublicPage::class);
        $reader->shouldReceive('fetch')->andThrow(new \RuntimeException('Unavailable'));
        $this->app->instance(PublicPage::class, $reader);
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])
            ->call('loadSitePages')->assertSet('sitePages', [])->assertSee('You can add a URL below.')
            ->set('pageUrl', 'https://coworkingspacehub.com/cardiff')->set('pageTitle', 'Cardiff')
            ->call('addPage')->assertHasNoErrors();
    }

    public function test_codex_mode_queues_research_without_an_anthropic_key(): void
    {
        config(['outreach.ai_provider' => 'codex']);
        $this->org->update(['llm_api_key' => null]);
        $this->page();
        $this->mock(CodexAssistant::class, function ($mock) {
            $mock->shouldReceive('status')->andReturn(true);
        });
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])
            ->assertSee('your Codex allowance')->call('research')->assertHasNoErrors();
        Queue::assertPushed(ResearchTargetPage::class);
        Livewire::test(AiSettings::class)->assertSee('Connected with ChatGPT')->assertDontSee('Anthropic API key');
    }

    public function test_codex_mode_prompts_for_login_when_disconnected(): void
    {
        config(['outreach.ai_provider' => 'codex']);
        $this->page();
        $this->mock(CodexAssistant::class, function ($mock) {
            $mock->shouldReceive('status')->andReturn(false);
        });
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])
            ->call('research')->assertHasErrors('research')->assertSee('Codex is not signed in');
        Queue::assertNotPushed(ResearchTargetPage::class);
        Livewire::test(AiSettings::class)->assertSee('codex login');
    }

    public function test_draft_is_saved_and_only_explicit_approval_queues_send(): void
    {
        $component = Livewire::test(ProjectWorkspace::class, ['project' => $this->project])->call('compose')->set('to', 'editor@example.org')->set('subject', 'A local resource')->set('body', 'Hello, here is our useful resource.')->call('saveDraft')->assertHasNoErrors();
        $message = OutreachMessage::first();
        $this->assertSame('draft', $message->status);
        $this->assertNull($message->approved_at);
        Queue::assertNotPushed(SendReviewedMessage::class);
        $component->call('send')->assertHasNoErrors();
        $this->assertSame('queued', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->approved_at);
        Queue::assertPushed(SendReviewedMessage::class, 1);
        $component->set('body', 'A repeated click must not send twice.')->call('send')->assertHasErrors('draft');
        Queue::assertPushed(SendReviewedMessage::class, 1);
    }

    public function test_ai_draft_is_reviewable_and_does_not_send(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['subject' => 'Cardiff resource', 'body' => 'Would this guide help your readers?'])]], 'stop_reason' => 'end_turn'])]);
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])->set('to', 'editor@example.org')->call('generateDraft')->assertSet('body', 'Would this guide help your readers?')->assertHasNoErrors();
        $this->assertDatabaseCount('outreach_messages', 0);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_reply_import_is_idempotent_and_matches_headers_within_account(): void
    {
        $thread = $this->thread();
        $this->message($thread);
        $data = ['message_id' => 'reply@example.org', 'in_reply_to' => '<original@example.com>', 'references' => '', 'from' => 'editor@example.org', 'to' => 'owner@example.com', 'subject' => 'Re: A useful resource', 'body' => 'Thanks, interested!', 'received_at' => now()];
        $mailbox = new Mailbox;
        $first = $mailbox->ingest($this->inbox, $data);
        $second = $mailbox->ingest($this->inbox, $data);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($thread->id, $first->conversation_id);
        $this->assertDatabaseCount('outreach_messages', 2);
        $this->assertNull($thread->fresh()->read_at);
        $other = $this->inbox->replicate();
        $other->email_address = 'other@example.com';
        $other->default_project_id = null;
        $other->save();
        $separate = $mailbox->ingest($other, $data);
        $this->assertNotSame($thread->id, $separate->conversation_id);
        $this->assertNull($separate->conversation->project_id);
    }

    public function test_unmatched_mail_can_be_assigned_to_project(): void
    {
        $thread = $this->thread();
        $thread->update(['project_id' => null]);
        Livewire::test(UnassignedMail::class)->set('projectsForThread', [$thread->id => $this->project->id])->call('assign', $thread->id)->assertHasNoErrors();
        $this->assertSame($this->project->id, $thread->fresh()->project_id);
    }

    public function test_another_organization_cannot_access_project_account_or_thread(): void
    {
        $org = Organization::create(['name' => 'Other', 'slug' => 'other']);
        $project = $org->projects()->create(['name' => 'Private', 'website_url' => 'https://example.org']);
        $this->get(route('projects.workspace', $project))->assertNotFound();
        $inbox = $this->inbox->replicate();
        $inbox->organization_id = $org->id;
        $inbox->save();
        $this->get(route('inboxes.edit', $inbox))->assertNotFound();
        $thread = $this->thread();
        $thread->update(['project_id' => $project->id]);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])->call('openThread', $thread->id);
    }

    public function test_poll_failure_preserves_last_successful_sync(): void
    {
        $date = now()->subDay()->startOfSecond();
        $this->inbox->update(['last_polled_at' => $date]);
        $gateway = Mockery::mock(ImapGateway::class);
        $gateway->shouldReceive('messages')->andThrow(new \RuntimeException('Bad credentials'));
        try {
            (new PollInboxForReplies($this->inbox))->handle($gateway, new Mailbox);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
        }
        $this->assertTrue($this->inbox->fresh()->last_polled_at->equalTo($date));
        $this->assertNotNull($this->inbox->fresh()->sync_error);
    }

    public function test_poll_success_imports_and_advances_checkpoint(): void
    {
        $gateway = Mockery::mock(ImapGateway::class);
        $gateway->shouldReceive('messages')->andReturn((function () {
            yield ['message_id' => 'new@example.org', 'from' => 'editor@example.org', 'to' => 'owner@example.com', 'subject' => 'Hello', 'body' => 'New opportunity', 'received_at' => now()];
        })());
        (new PollInboxForReplies($this->inbox))->handle($gateway, new Mailbox);
        $this->assertNotNull($this->inbox->fresh()->last_polled_at);
        $this->assertDatabaseCount('outreach_messages', 1);
    }

    public function test_send_job_requires_approval_and_uses_chosen_account_once(): void
    {
        $message = $this->message($this->thread(), ['status' => 'queued', 'approved_at' => null, 'sent_at' => null]);
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldNotReceive('make');
        (new SendReviewedMessage($message))->handle($factory);
        $this->assertSame('queued', $message->fresh()->status);
        $message->update(['approved_at' => now()]);
        $mailer = Mockery::mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->withArgs(function ($email) {
            return $email->getTo()[0]->getAddress() === 'editor@example.org' && $email->getFrom()[0]->getAddress() === 'owner@example.com';
        });
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldReceive('make')->once()->withArgs(fn ($inbox) => $inbox->id === $this->inbox->id)->andReturn($mailer);
        $job = new SendReviewedMessage($message);
        $this->assertSame('outgoing', $job->queue);
        $job->handle($factory);
        $job->handle($factory);
        $this->assertSame('sent', $message->fresh()->status);
    }

    public function test_send_job_uses_alias_and_records_actual_sender(): void
    {
        $this->inbox->update(['provider' => 'yahoo', 'email_address' => 'owner@yahoo.com', 'smtp_username' => 'owner@yahoo.com', 'from_address' => 'hello@academicecologyjobs.com']);
        $message = $this->message($this->thread(), ['status' => 'queued', 'approved_at' => now(), 'sent_at' => null]);
        $mailer = Mockery::mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->withArgs(fn ($email, $envelope) => $email->getFrom()[0]->getAddress() === 'hello@academicecologyjobs.com'
            && $envelope->getSender()->getAddress() === 'owner@yahoo.com'
            && $envelope->getRecipients()[0]->getAddress() === 'editor@example.org');
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldReceive('make')->once()->withArgs(fn ($inbox) => $inbox->smtp_username === 'owner@yahoo.com')->andReturn($mailer);
        (new SendReviewedMessage($message))->handle($factory);
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('hello@academicecologyjobs.com', $message->fresh()->from_email);
    }

    public function test_suppressed_recipient_never_reaches_smtp(): void
    {
        Suppression::create(['organization_id' => $this->org->id, 'email' => 'editor@example.org', 'reason' => 'manual']);
        $message = $this->message($this->thread(), ['status' => 'queued', 'approved_at' => now(), 'sent_at' => null]);
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldNotReceive('make');
        (new SendReviewedMessage($message))->handle($factory);
        $this->assertSame('failed', $message->fresh()->status);
    }

    public function test_research_stores_live_evidence_and_rejects_invented_urls(): void
    {
        $run = ResearchRun::create(['target_page_id' => $this->page()->id]);
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->andReturn('Useful coworking guide');
        $source = 'https://directory.example.org/cardiff';
        $items = [['url' => $source, 'title' => 'Cardiff directory', 'relevance' => 'Local audience', 'attainability' => 'Public submissions page', 'angle' => 'Add a useful guide', 'source_urls' => [$source]], ['url' => 'https://invented.example.org', 'title' => 'Invented', 'source_urls' => [$source]]];
        Http::fake(['api.anthropic.com/*' => Http::sequence()->push(['content' => [['type' => 'web_search_tool_result', 'content' => [['type' => 'web_search_result', 'url' => $source, 'title' => 'Directory']]], ['type' => 'text', 'text' => 'A relevant directory.', 'citations' => [['url' => $source, 'title' => 'Directory', 'cited_text' => 'Submit a resource']]]], 'stop_reason' => 'end_turn'])->push(['content' => [['type' => 'text', 'text' => json_encode(['opportunities' => $items])]], 'stop_reason' => 'end_turn'])]);
        (new ResearchTargetPage($run))->handle(new Assistant, $pages);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertDatabaseCount('opportunities', 1);
        $this->assertSame('Submit a resource', Opportunity::first()->sources[0]['quote']);
        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'test-secret') && $r->hasHeader('anthropic-version', '2023-06-01'));
    }

    public function test_search_tool_error_is_not_presented_as_success(): void
    {
        $run = ResearchRun::create(['target_page_id' => $this->page()->id]);
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->andReturn('Content');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'web_search_tool_result', 'content' => ['error_code' => 'unavailable']]], 'stop_reason' => 'end_turn'])]);
        (new ResearchTargetPage($run))->handle(new Assistant, $pages);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_link_checker_requires_an_actual_anchor_and_detects_loss(): void
    {
        $op = Opportunity::create(['target_page_id' => $this->page()->id, 'url' => 'https://directory.example.org', 'title' => 'Directory', 'relevance' => 'Local', 'attainability' => 'Open', 'angle' => 'Useful', 'sources' => []]);
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('fetch')->andReturn('<a href="https://coworkingspacehub.com/cardiff">Guide</a>', 'Just text https://coworkingspacehub.com/cardiff');
        $job = new CheckOpportunityLink($op);
        $job->handle($pages);
        $this->assertSame('found', $op->fresh()->link_status);
        $job->handle($pages);
        $this->assertSame('lost', $op->fresh()->link_status);
    }

    public function test_provider_settings_save_encrypted_credentials_and_assignments(): void
    {
        $this->mock(MailProviderDetector::class, function ($mock) {
            $mock->shouldReceive('detect')->with('owner@site.example')->andReturn('zoho_pro');
        });
        $this->mock(MailConnectionVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')->once();
        });
        Livewire::test(InboxForm::class)->set('email_address', 'owner@site.example')->call('identify')->set('name', 'Zoho')->set('from_name', 'Owner')->set('password', 'app-password')->set('projectIds', [$this->project->id])->set('defaultProjectId', $this->project->id)->call('save')->assertHasNoErrors();
        $inbox = EmailInbox::where('name', 'Zoho')->firstOrFail();
        $this->assertSame('smtppro.zoho.com', $inbox->smtp_host);
        $this->assertSame('imappro.zoho.com', $inbox->imap_host);
        $this->assertSame('app-password', $inbox->smtp_password);
        $this->assertNotSame('app-password', DB::table('email_inboxes')->where('id', $inbox->id)->value('smtp_password'));
        $this->assertTrue($inbox->projects->contains($this->project));
        Livewire::test(InboxForm::class, ['id' => $inbox->id])->set('name', 'Renamed')->call('save')->assertHasNoErrors();
        $this->assertSame('app-password', $inbox->fresh()->smtp_password);
    }

    public function test_settings_preserve_key_when_blank_and_never_expose_it(): void
    {
        Livewire::test(AiSettings::class)->assertSet('apiKey', '')->set('model', 'claude-sonnet-4-6')->call('save')->assertHasNoErrors();
        $this->assertSame('test-secret', $this->org->fresh()->llm_api_key);
        $this->assertArrayNotHasKey('llm_api_key', $this->org->toArray());
    }

    public function test_failed_smtp_delivery_is_not_automatically_retried(): void
    {
        $message = $this->message($this->thread(), ['status' => 'queued', 'approved_at' => now(), 'sent_at' => null]);
        $mailer = Mockery::mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('Connection lost after DATA'));
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($mailer);
        $job = new SendReviewedMessage($message);
        $job->handle($factory);
        $job->handle($factory);
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertNull($message->fresh()->sent_at);
        $this->assertStringContainsString('connection', strtolower($message->fresh()->error));
        $this->assertStringContainsString('will not be retried automatically', $message->fresh()->error);
        $this->assertStringContainsString('Stage: unknown', $message->fresh()->error);
        $this->assertStringContainsString('Error type: RuntimeException', $message->fresh()->error);
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])
            ->call('openThread', $message->conversation_id)
            ->assertSee('Sending failed — delivery not confirmed')
            ->assertSee('Sending failed · 1 email');
    }

    public function test_hourly_limit_leaves_approved_message_queued(): void
    {
        $this->inbox->update(['max_sends_per_hour' => 1]);
        $thread = $this->thread();
        $this->message($thread);
        $pending = $this->message($thread, ['message_id' => 'pending@example.com', 'status' => 'queued', 'approved_at' => now(), 'sent_at' => null]);
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldNotReceive('make');
        (new SendReviewedMessage($pending))->handle($factory);
        $this->assertSame('queued', $pending->fresh()->status);
    }

    public function test_reply_uses_thread_headers_and_markup_stays_escaped(): void
    {
        $thread = $this->thread();
        $this->message($thread, ['direction' => 'inbound', 'status' => 'received', 'received_at' => now(), 'body' => '<script>alert("mail")</script>']);
        Livewire::test(ProjectWorkspace::class, ['project' => $this->project])->call('openThread', $thread->id)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("mail")</script>', false)
            ->set('body', 'Thanks for your reply.')->call('saveDraft')->assertHasNoErrors();
        $draft = OutreachMessage::where('status', 'draft')->firstOrFail();
        $this->assertSame('original@example.com', $draft->in_reply_to);
        $this->assertStringContainsString('<original@example.com>', $draft->references);
    }

    public function test_private_target_fetch_is_rejected_without_a_request(): void
    {
        try {
            (new PublicPage)->fetch('http://127.0.0.1/private');
            $this->fail('Private address must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('public', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_project_dashboard_reports_new_workspace_activity(): void
    {
        $thread = $this->thread();
        $this->message($thread, ['direction' => 'inbound', 'status' => 'received', 'received_at' => now(), 'body' => 'Hello']);
        $this->get(route('dashboard'))->assertOk()->assertSee('Unread conversations')->assertSee('Coworking Space Hub');
    }
}
