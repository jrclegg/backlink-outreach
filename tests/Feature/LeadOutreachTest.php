<?php

namespace Tests\Feature;

use App\Jobs\DraftLeadEmail;
use App\Jobs\ResearchLeadBatch;
use App\Jobs\SendReviewedMessage;
use App\Models\Conversation;
use App\Models\LeadCampaign;
use App\Models\Organization;
use App\Models\User;
use App\Services\Mail\DynamicMailerFactory;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\LeadOutreach;
use App\Services\Outreach\Mailbox;
use App\Services\Outreach\PublicPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class LeadOutreachTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    private $inbox;

    private $campaign;

    private string $url = 'https://invoicecustomiser.com/try';

    private function body(): string
    {
        return "Hi Sam,\n\nI was looking at Brightline Studio and saw you design brand identities for independent cafes and bakeries around Leeds.\n\nI'm Jonathan, and I made Invoice Customiser, a simple tool for laying out your own invoices on an A4 page. You place your logo, details and line items where you like, and it works out totals and UK VAT, numbers each invoice and gives you a PDF.\n\nIt's £79 once, with no subscription, and you can try a sample invoice without signing up:\n{$this->url}\n\nNo worries if it's not for you.\n\nThanks,\nJonathan";
    }

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
        $this->actingAs(User::factory()->create(['current_organization_id' => $org->id]));
        $this->project = $org->projects()->create(['name' => 'Invoice Customiser', 'website_url' => 'https://invoicecustomiser.com']);
        $this->inbox = $org->emailInboxes()->create(['name' => 'Invoice Customiser', 'email_address' => 'jonathan@naturetech.io', 'from_address' => 'jonathan@invoicecustomiser.com', 'from_name' => 'Jonathan', 'is_active' => true, 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'jonathan@naturetech.io', 'smtp_password' => 'test-password', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'jonathan@naturetech.io', 'imap_password' => 'test-password']);
        $this->project->emailInboxes()->attach($this->inbox);
        $this->campaign = LeadCampaign::create(['project_id' => $this->project->id, 'email_inbox_id' => $this->inbox->id, 'audience' => 'Small UK limited companies', 'offer' => 'Invoice designer, £79 once', 'product_url' => $this->url, 'sender' => 'Jonathan', 'enabled' => true, 'auto_send' => true, 'daily_limit' => 10]);
    }

    private function prospect(array $overrides = [])
    {
        $batch = $this->campaign->batches()->firstOrCreate(['day' => now('Europe/London')->toDateString()], ['status' => 'drafting']);

        return $batch->prospects()->create(array_merge(['lead_campaign_id' => $this->campaign->id, 'organization' => 'Brightline Studio', 'domain' => 'brightline.test', 'contact_name' => 'Sam Patel', 'role' => 'Director', 'email' => 'sam@brightline.test', 'source_url' => 'https://brightline.test/contact', 'detail_url' => 'https://brightline.test/about', 'detail' => 'Brand identities for independent cafes and bakeries in Leeds'], $overrides));
    }

    private function pages(string $footer = 'Brightline Studio Ltd, company number 01234567'): void
    {
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->with('https://brightline.test/contact')->andReturn("Sam Patel, Director. Email sam@brightline.test. $footer");
        $pages->shouldReceive('text')->with('https://brightline.test/about')->andReturn('We design brand identities for independent cafes and bakeries around Leeds.');
        $this->app->instance(PublicPage::class, $pages);
    }

    private function review(int $score = 9): array
    {
        return ['scores' => array_fill_keys(DraftLeadEmail::SCORES, $score), 'contact_suitable' => true, 'detail_evidence' => 'brand identities for independent cafes and bakeries', 'weaknesses' => '', 'reason' => 'Specific and honest.'];
    }

    public function test_research_keeps_only_cited_contacts_on_the_business_domain(): void
    {
        $this->campaign->batches()->create(['day' => now('Europe/London')->toDateString()]);
        $row = fn ($org, $email, $site) => ['organization' => $org, 'contact_name' => '', 'role' => '', 'email' => $email, 'source_url' => "https://$site/contact", 'detail_url' => "https://$site/about", 'detail' => 'Wedding photography in Cornwall'];
        $prospects = [$row('Good', 'hello@good.test', 'good.test'), $row('NoReply', 'noreply@nr.test', 'nr.test'), $row('Gmail', 'pat@gmail.com', 'offdomain.test')];
        $citations = collect($prospects)->flatMap(fn ($p) => [['url' => $p['source_url']], ['url' => $p['detail_url']]])->all();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('request')->once()->andReturn(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'x', 'citations' => $citations]]]);
        $ai->shouldReceive('text')->andReturn(json_encode(['prospects' => $prospects]));
        (new ResearchLeadBatch($this->campaign->batches()->first()))->handle($ai);
        $this->assertSame(['hello@good.test'], $this->campaign->prospects()->pluck('email')->all());
        Queue::assertPushed(DraftLeadEmail::class, 1);
    }

    public function test_passing_draft_is_sent_automatically_only_while_enabled(): void
    {
        $this->pages();
        $p = $this->prospect();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->andReturn(['subject' => 'Invoices for Brightline Studio', 'body' => $this->body()], $this->review());
        (new DraftLeadEmail($p))->handle($ai, app(PublicPage::class), app(LeadOutreach::class));
        $p->refresh();
        $this->assertSame('approved', $p->status, (string) $p->reason);
        $this->assertSame('queued', $p->message->status);
        $this->assertSame('jonathan@invoicecustomiser.com', $p->message->from_email);
        $this->assertStringEndsWith(DraftLeadEmail::OPT_OUT, $p->message->body);
        Queue::assertPushed(SendReviewedMessage::class, 1);
        $this->campaign->update(['enabled' => false]);
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldNotReceive('make');
        (new SendReviewedMessage($p->message))->handle($factory);
        $this->assertSame('failed', $p->message->fresh()->status);
    }

    public function test_poor_fit_businesses_are_skipped(): void
    {
        $this->pages('Sam Patel trading as Brightline Studio');
        $p = $this->prospect();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->twice()->andReturn(['subject' => 'Invoices for Brightline Studio', 'body' => $this->body()], ['scores' => ['relevant' => 2] + $this->review()['scores']] + $this->review());
        (new DraftLeadEmail($p))->handle($ai, app(PublicPage::class), app(LeadOutreach::class));
        $this->assertSame('skipped', $p->fresh()->status);
        $this->assertStringContainsString('does not fit', $p->fresh()->reason);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_low_scores_are_kept_for_review_not_sent(): void
    {
        $this->pages();
        $p = $this->prospect();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->times(4)->andReturn(['subject' => 'Invoices for Brightline Studio', 'body' => $this->body()], $this->review(7), ['subject' => 'Invoices for Brightline Studio', 'body' => $this->body()], $this->review(7));
        (new DraftLeadEmail($p))->handle($ai, app(PublicPage::class), app(LeadOutreach::class));
        $this->assertSame('needs_review', $p->fresh()->status);
        $this->assertSame('draft', $p->fresh()->message->status);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_rules_require_the_one_product_link_and_reject_free_trial(): void
    {
        $job = new DraftLeadEmail($this->prospect());
        $p = $this->prospect(['domain' => 'x.test', 'email' => 'x@x.test']);
        $this->assertSame([], $job->ruleProblems('Invoices for Brightline Studio', $this->body(), $p, 'Jonathan', $this->url));
        $this->assertContains('Remove "free trial".', $job->ruleProblems('Invoices', str_replace('try a sample invoice', 'start a free trial', $this->body()), $p, 'Jonathan', $this->url));
        $this->assertContains("Include exactly one link: {$this->url}.", $job->ruleProblems('Invoices', str_replace($this->url, 'https://evil.test', $this->body()), $p, 'Jonathan', $this->url));
    }

    public function test_shared_mailbox_routes_replies_to_the_inbox_they_were_sent_to(): void
    {
        $main = $this->inbox->organization->emailInboxes()->create(['name' => 'NatureTech', 'email_address' => 'jonathan@naturetech.io', 'from_name' => 'Jonathan', 'is_active' => true, 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'jonathan@naturetech.io', 'smtp_password' => 'x', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'jonathan@naturetech.io', 'imap_password' => 'x']);
        $mail = fn ($to) => ['from' => 'sam@brightline.test', 'to' => $to, 'subject' => 'Re: Invoices', 'body' => 'Thanks', 'message_id' => uniqid().'@brightline.test', 'received_at' => now()->toDateTimeString()];
        $box = app(Mailbox::class);
        $this->assertNotNull($box->ingest($this->inbox, $mail('jonathan@invoicecustomiser.com')));
        $this->assertNull($box->ingest($main, $mail('jonathan@invoicecustomiser.com')));
        $this->assertNull($box->ingest($this->inbox, $mail('jonathan@naturetech.io')));
        $this->assertNotNull($box->ingest($main, $mail('jonathan@naturetech.io')));
    }
}
