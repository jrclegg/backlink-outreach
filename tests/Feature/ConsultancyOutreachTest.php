<?php

namespace Tests\Feature;

use App\Jobs\DraftConsultancyEmail;
use App\Jobs\ResearchConsultancyBatch;
use App\Jobs\SendReviewedMessage;
use App\Models\ConsultancyCampaign;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Mail\DynamicMailerFactory;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\ConsultancyOutreach;
use App\Services\Outreach\PublicPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ConsultancyOutreachTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    private $inbox;

    private $campaign;

    private string $body = "Hi Rossa,\n\nI came across Wessex Ecology and saw that you run long-term dormouse monitoring for several estates across Dorset.\n\nI'm a software developer with a background in zoology and herpetology, and I've been speaking to ecology consultancies to understand the challenges they encounter in their everyday work.\n\nWith monitoring running over several years, how do you keep track of survey results from one season to the next?\n\nThanks,\nJonathan";

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
        $this->actingAs(User::factory()->create(['current_organization_id' => $org->id]));
        $this->project = $org->projects()->create(['name' => 'Nature Tech Jobs', 'website_url' => 'https://naturetech.io']);
        $this->inbox = $org->emailInboxes()->create(['name' => 'NatureTech', 'email_address' => 'jonathan@naturetech.io', 'from_name' => 'Jonathan', 'is_active' => true, 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'jonathan@naturetech.io', 'smtp_password' => 'test-password', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'jonathan@naturetech.io', 'imap_password' => 'test-password']);
        $this->project->emailInboxes()->attach($this->inbox);
        $this->campaign = ConsultancyCampaign::create(['project_id' => $this->project->id, 'email_inbox_id' => $this->inbox->id, 'audience' => 'Small UK ecology consultancies', 'sender' => 'Jonathan, software developer', 'examples' => 'Hi James, ...', 'enabled' => true, 'daily_limit' => 3, 'excluded_domains' => ['skopeo.co.uk']]);
    }

    private function prospect(array $overrides = [])
    {
        $batch = $this->campaign->batches()->firstOrCreate(['day' => now('Europe/London')->toDateString()], ['status' => 'drafting']);

        return $batch->prospects()->create(array_merge(['consultancy_campaign_id' => $this->campaign->id, 'organization' => 'Wessex Ecology', 'domain' => 'wessexecology.co.uk', 'contact_name' => 'Rossa Smith', 'role' => 'Director', 'email' => 'rossa@wessexecology.co.uk', 'source_url' => 'https://wessexecology.co.uk/team', 'detail_url' => 'https://wessexecology.co.uk/projects', 'detail' => 'Long-term dormouse monitoring for estates in Dorset'], $overrides));
    }

    private function pages(): void
    {
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->with('https://wessexecology.co.uk/team')->andReturn('Rossa Smith, Director. Email rossa@wessexecology.co.uk');
        $pages->shouldReceive('text')->with('https://wessexecology.co.uk/projects')->andReturn('We run long-term dormouse monitoring for several estates across Dorset.');
        $this->app->instance(PublicPage::class, $pages);
    }

    private function review(int $score = 9): array
    {
        return ['scores' => array_fill_keys(DraftConsultancyEmail::SCORES, $score), 'contact_suitable' => true, 'detail_evidence' => 'long-term dormouse monitoring for several estates', 'weaknesses' => 'The question is vague.', 'reason' => 'Specific and natural.'];
    }

    public function test_research_keeps_only_named_contacts_on_the_firms_own_domain(): void
    {
        $this->campaign->batches()->create(['day' => now('Europe/London')->toDateString()]);
        $sent = Conversation::create(['project_id' => $this->project->id, 'email_inbox_id' => $this->inbox->id, 'contact_email' => 'chris@footprint.test', 'subject' => 'Hello']);
        $sent->messages()->create(['email_inbox_id' => $this->inbox->id, 'direction' => 'outbound', 'status' => 'sent', 'from_email' => 'jonathan@naturetech.io', 'to_email' => 'chris@footprint.test', 'subject' => 'Hello', 'body' => 'Hi', 'message_id' => 'a@naturetech.io', 'sent_at' => now()]);
        $row = fn ($org, $email, $site) => ['organization' => $org, 'contact_name' => 'Pat Lee', 'role' => 'Director', 'email' => $email, 'source_url' => "https://$site/team", 'detail_url' => "https://$site/about", 'detail' => 'Uses a network of associates'];
        $prospects = [$row('Good', 'pat@good.test', 'good.test'), $row('Shared', 'info@shared.test', 'shared.test'), $row('Gmail', 'pat@gmail.com', 'offdomain.test'), $row('Footprint', 'other@footprint.test', 'footprint.test'), $row('Skopeo', 'pat@skopeo.co.uk', 'skopeo.co.uk')];
        $citations = collect($prospects)->flatMap(fn ($p) => [['url' => $p['source_url']], ['url' => $p['detail_url']]])->all();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('request')->once()->andReturn(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'x', 'citations' => $citations]]]);
        $ai->shouldReceive('text')->andReturn(json_encode(['prospects' => $prospects]));
        (new ResearchConsultancyBatch($this->campaign->batches()->first()))->handle($ai);
        $this->assertSame(['pat@good.test'], $this->campaign->prospects()->pluck('email')->all());
        Queue::assertPushed(DraftConsultancyEmail::class, 1);
    }

    public function test_rewrites_once_then_saves_draft_for_approval_without_sending(): void
    {
        $this->pages();
        $p = $this->prospect();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->andReturn(
            ['subject' => 'Quick question', 'body' => "Hi Rossa,\n\nWe've built a platform. Book a demo?\n\nJonathan"],
            ['subject' => 'Dormouse monitoring records', 'body' => $this->body],
            $this->review(),
        );
        (new DraftConsultancyEmail($p))->handle($ai, app(PublicPage::class), app(ConsultancyOutreach::class));
        $p->refresh();
        $this->assertSame('passed', $p->status);
        $this->assertSame('draft', $p->message->status);
        $this->assertStringEndsWith(DraftConsultancyEmail::OPT_OUT, $p->message->body);
        $this->assertSame($this->body, $p->ai_draft);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_drafts_that_score_below_nine_twice_are_kept_for_review_not_sent(): void
    {
        $this->pages();
        $p = $this->prospect();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->times(4)->andReturn(['subject' => 'Dormouse monitoring records', 'body' => $this->body], $this->review(8), ['subject' => 'Dormouse monitoring records', 'body' => $this->body], $this->review(8));
        (new DraftConsultancyEmail($p))->handle($ai, app(PublicPage::class), app(ConsultancyOutreach::class));
        $p->refresh();
        $this->assertSame('needs_review', $p->status);
        $this->assertSame('draft', $p->message->status);
        $this->assertStringContainsString('check before sending', $p->reason);
        $this->assertFalse(app(ConsultancyOutreach::class)->approve($p));
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_automatic_mode_sends_only_while_enabled_and_never_twice_to_a_firm(): void
    {
        $this->pages();
        $this->campaign->update(['auto_send' => true]);
        $p = $this->prospect();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->andReturn(['subject' => 'Dormouse monitoring records', 'body' => $this->body], $this->review());
        (new DraftConsultancyEmail($p))->handle($ai, app(PublicPage::class), app(ConsultancyOutreach::class));
        $message = $p->fresh()->message;
        $this->assertSame('approved', $p->fresh()->status);
        $this->assertSame('queued', $message->status);
        Queue::assertPushed(SendReviewedMessage::class, 1);
        $this->campaign->update(['auto_send' => false]);
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldNotReceive('make');
        (new SendReviewedMessage($message))->handle($factory);
        $this->assertSame('failed', $message->fresh()->status);

        $other = Conversation::create(['project_id' => $this->project->id, 'email_inbox_id' => $this->inbox->id, 'contact_email' => 'kim@wessexecology.co.uk', 'subject' => 'Earlier']);
        $other->messages()->create(['email_inbox_id' => $this->inbox->id, 'direction' => 'outbound', 'status' => 'sent', 'from_email' => 'jonathan@naturetech.io', 'to_email' => 'kim@wessexecology.co.uk', 'subject' => 'Earlier', 'body' => 'Hi', 'message_id' => 'b@naturetech.io', 'sent_at' => now()]);
        $this->assertFalse(app(ConsultancyOutreach::class)->eligible($p->fresh()));
    }

    public function test_banned_phrases_match_whole_words_only(): void
    {
        $job = new DraftConsultancyEmail($this->prospect());
        $this->assertSame([], $job->ruleProblems('Dormouse monitoring records', str_replace('keep track', 'demonstrate', $this->body), $this->prospect(['domain' => 'x.test', 'email' => 'x@x.test']), 'Jonathan'));
        $this->assertContains('Remove "demo".', $job->ruleProblems('Dormouse monitoring records', str_replace('keep track', 'demo', $this->body), $this->prospect(['domain' => 'y.test', 'email' => 'y@y.test']), 'Jonathan'));
    }
}
