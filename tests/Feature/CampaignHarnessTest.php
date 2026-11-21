<?php

namespace Tests\Feature;

use App\Jobs\DraftLeadEmail;
use App\Jobs\ResearchLeadBatch;
use App\Jobs\SendReviewedMessage;
use App\Livewire\Outreach\CampaignEditor;
use App\Livewire\Outreach\CampaignIndex;
use App\Livewire\Outreach\CampaignShow;
use App\Models\Conversation;
use App\Models\LeadCampaign;
use App\Models\Organization;
use App\Models\User;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\LeadOutreach;
use App\Services\Outreach\PublicPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CampaignHarnessTest extends TestCase
{
    use RefreshDatabase;

    private $org;

    private $project;

    private $inbox;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->org = Organization::create(['name' => 'Test', 'slug' => 'test', 'llm_api_key' => 'k']);
        config(['outreach.ai_provider' => 'anthropic']);
        $this->actingAs(User::factory()->create(['current_organization_id' => $this->org->id]));
        $this->project = $this->org->projects()->create(['name' => 'Beat Darwin', 'website_url' => 'https://beatdarwin.test']);
        $this->inbox = $this->org->emailInboxes()->create(['name' => 'BD', 'email_address' => 'me@beatdarwin.test', 'from_name' => 'Jonathan', 'is_active' => true, 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'me', 'smtp_password' => 'x', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'me', 'imap_password' => 'x']);
    }

    private function campaign(array $overrides = [], $project = null): LeadCampaign
    {
        $project ??= $this->project;
        $project->emailInboxes()->syncWithoutDetaching([$this->inbox->id]);

        return LeadCampaign::create(array_merge(['project_id' => $project->id, 'email_inbox_id' => $this->inbox->id, 'name' => 'Teaching links', 'goal' => 'link', 'audience' => 'Biology teaching blogs', 'offer' => 'A free evolution quiz', 'product_url' => 'https://beatdarwin.test/quiz', 'sender' => 'Jonathan, who built it', 'fit_rules' => 'Only pages aimed at teachers.', 'email_rules' => 'Never offer payment for links.', 'enabled' => true], $overrides));
    }

    private function contact(LeadCampaign $c, string $domain)
    {
        $thread = Conversation::create(['project_id' => $c->project_id, 'email_inbox_id' => $this->inbox->id, 'contact_email' => "ed@$domain", 'subject' => 'Hi']);
        $thread->messages()->create(['email_inbox_id' => $this->inbox->id, 'direction' => 'outbound', 'status' => 'sent', 'from_email' => 'me@beatdarwin.test', 'to_email' => "ed@$domain", 'subject' => 'Hi', 'body' => 'x', 'message_id' => uniqid().'@beatdarwin.test']);
    }

    private function prospect(LeadCampaign $c, string $domain = 'site.test')
    {
        $batch = $c->batches()->firstOrCreate(['day' => now('Europe/London')->toDateString()], ['status' => 'ready']);

        return $batch->prospects()->create(['lead_campaign_id' => $c->id, 'organization' => 'Science Site', 'domain' => $domain, 'email' => "ed@$domain", 'source_url' => "https://$domain/contact", 'detail_url' => "https://$domain/resources", 'detail' => 'A list of evolution resources']);
    }

    public function test_a_project_can_run_several_campaigns_with_their_own_rules(): void
    {
        $links = $this->campaign();
        $sales = $this->campaign(['name' => 'Schools', 'goal' => 'conversation', 'fit_rules' => 'Secondary schools only.', 'email_rules' => 'Mention it is free.', 'min_words' => 50, 'max_words' => 90]);
        $this->assertSame(2, $this->project->leadCampaigns()->count());

        $research = ResearchLeadBatch::prompt($links, 6, 'university outreach pages');
        $this->assertStringContainsString('Earn a link', $research);
        $this->assertStringContainsString('Only pages aimed at teachers.', $research);
        $this->assertStringNotContainsString('invoice', strtolower($research));

        $draft = DraftLeadEmail::draftPrompt($links, 'Hi there,', null, []);
        $this->assertStringContainsString('Never offer payment for links.', $draft);
        $this->assertStringContainsString('no request for a call', $draft);
        $talk = DraftLeadEmail::draftPrompt($sales, 'Hi there,', null, []);
        $this->assertStringContainsString('50-90 words', $talk);
        $this->assertStringNotContainsString('no request for a call', $talk);
        $this->assertStringContainsString('Mention it is free.', DraftLeadEmail::reviewPrompt($sales, []));
    }

    public function test_word_limits_come_from_the_campaign(): void
    {
        $c = $this->campaign(['min_words' => 10, 'max_words' => 20]);
        $p = $this->prospect($c);
        $body = "Hi there,\n\nScience Site has a great list.\n\nhttps://beatdarwin.test/quiz\n\nThanks,\nJonathan";
        $problems = (new DraftLeadEmail($p))->ruleProblems('Quiz', $body, $p, 'Jonathan', $c->product_url);
        $this->assertEmpty(array_filter($problems, fn ($x) => str_starts_with($x, 'Body is')));
        $c->update(['min_words' => 30, 'max_words' => 60]);
        $problems = (new DraftLeadEmail($p->fresh()))->ruleProblems('Quiz', $body, $p->fresh(), 'Jonathan', $c->product_url);
        $this->assertNotEmpty(array_filter($problems, fn ($x) => str_contains($x, 'it must be 30-60')));
    }

    public function test_client_contact_history_is_kept_separate(): void
    {
        $mine = $this->campaign();
        $other = $this->campaign(['name' => 'Other'], $this->org->projects()->create(['name' => 'Coworking', 'website_url' => 'https://cowork.test']));
        $client = $this->campaign(['name' => 'Recruiter'], $this->org->projects()->create(['name' => 'Smith', 'website_url' => 'https://smith.test', 'client_name' => 'Smith Recruitment']));
        $outreach = app(LeadOutreach::class);

        $this->contact($other, 'shared.test');
        $this->assertFalse($outreach->eligible($this->prospect($mine, 'shared.test')), 'your projects share one contact history');
        $this->assertTrue($outreach->eligible($this->prospect($client, 'shared.test')), 'your contacts do not block a client');

        $this->contact($client, 'clientonly.test');
        $this->assertFalse($outreach->eligible($this->prospect($client, 'clientonly.test')));
        $this->assertTrue($outreach->eligible($this->prospect($mine, 'clientonly.test')), 'a client contact does not block you');
    }

    public function test_campaign_is_created_from_a_plain_english_description(): void
    {
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->andReturn('Smith Recruitment places ecologists across the UK.');
        $this->app->instance(PublicPage::class, $pages);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn(['name' => 'Hiring managers', 'goal' => 'sales', 'audience' => 'UK ecological consultancies hiring', 'offer' => 'Smith places ecologists', 'product_url' => 'https://smith.test', 'sender' => 'Sam at Smith', 'focuses' => ['bat surveys', ''], 'fit_rules' => 'Hiring now', 'email_rules' => 'No fees', 'excluded_domains' => ['https://www.Rival.test/'], 'min_words' => 60, 'max_words' => 110, 'questions' => ['What is the fee?']]);
        $this->app->instance(Assistant::class, $ai);

        Livewire::test(CampaignEditor::class)
            ->set('projectId', 0)->assertSet('newProject', true)
            ->set('newProjectName', 'Smith')->set('newProjectUrl', 'https://smith.test')->set('newClientName', 'Smith Recruitment')
            ->set('description', 'Find ecological consultancies that are hiring and pitch our recruitment service.')
            ->call('writeBrief')->assertHasNoErrors()
            ->assertSet('focuses', 'bat surveys')->assertSet('excludedDomains', 'rival.test')->assertSet('questions', ['What is the fee?'])
            ->set('inboxId', $this->inbox->id)
            ->call('save')->assertHasNoErrors();

        $c = LeadCampaign::where('name', 'Hiring managers')->firstOrFail();
        $this->assertSame('Smith Recruitment', $c->project->client_name);
        $this->assertSame('off', $c->mode());
        $this->assertSame(['rival.test'], $c->excluded_domains);
        $this->assertTrue($c->project->emailInboxes()->whereKey($this->inbox->id)->exists());
        Livewire::test(CampaignIndex::class)->assertSee('Hiring managers')->assertSee('Client: Smith Recruitment');
    }

    public function test_drafts_are_approved_edited_or_skipped_from_the_campaign_page(): void
    {
        $c = $this->campaign(['enabled' => false]);
        $page = Livewire::test(CampaignShow::class, ['campaign' => $c])->call('runNow')->assertHasErrors('run');
        $page->call('setMode', 'approval');
        $this->assertSame('approval', $c->fresh()->mode());
        $page->call('runNow')->assertHasNoErrors();
        Queue::assertPushed(ResearchLeadBatch::class, 1);

        foreach (['a.test', 'b.test'] as $domain) {
            $p = $this->prospect($c, $domain);
            $thread = Conversation::create(['project_id' => $c->project_id, 'email_inbox_id' => $this->inbox->id, 'contact_email' => $p->email, 'subject' => 'Quiz']);
            $m = $thread->messages()->create(['email_inbox_id' => $this->inbox->id, 'direction' => 'outbound', 'status' => 'draft', 'from_email' => 'me@beatdarwin.test', 'to_email' => $p->email, 'subject' => 'Quiz', 'body' => 'Draft', 'message_id' => uniqid().'@beatdarwin.test']);
            $p->update(['status' => 'passed', 'outreach_message_id' => $m->id]);
        }
        [$a, $b] = $c->prospects()->orderBy('id')->get()->all();

        $page = Livewire::test(CampaignShow::class, ['campaign' => $c])->assertSee('Science Site')
            ->set("edits.{$a->id}.body", 'Edited by me')->call('approve', $a->id)->assertHasNoErrors()
            ->call('skip', $b->id);
        $this->assertSame('queued', $a->message->fresh()->status);
        $this->assertSame('Edited by me', $a->message->fresh()->body);
        Queue::assertPushed(SendReviewedMessage::class, 1);
        $this->assertSame('skipped', $b->fresh()->status);
        $this->assertSame('archived', $b->message->conversation->fresh()->status);
    }

    public function test_other_organisations_cannot_open_a_campaign(): void
    {
        $c = $this->campaign();
        $other = Organization::create(['name' => 'Other', 'slug' => 'other']);
        $this->actingAs(User::factory()->create(['current_organization_id' => $other->id]));
        $this->get(route('outreach.show', $c))->assertNotFound();
        $this->get(route('outreach.edit', $c))->assertNotFound();
    }
}
