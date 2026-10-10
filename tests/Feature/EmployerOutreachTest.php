<?php

namespace Tests\Feature;

use App\Jobs\DraftEmployerEmail;
use App\Jobs\AutoSendEmployerEmail;
use App\Jobs\PrepareEmployerBatch;
use App\Jobs\ResearchEmployerContacts;
use App\Jobs\SendReviewedMessage;
use App\Livewire\Workspace\EmployerOutreachWorkspace;
use App\Models\Conversation;
use App\Models\EmployerCampaign;
use App\Models\Organization;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Outreach\AcademicEmployerLeads;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\EmployerOutreach;
use App\Services\Outreach\PublicPage;
use App\Services\Mail\DynamicMailerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use PDO;
use Tests\TestCase;

class EmployerOutreachTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    private $inbox;

    private $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['outreach.academic_jobs_database' => null, 'outreach.academic_jobs_feed' => null]);
        $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
        $this->actingAs(User::factory()->create(['current_organization_id' => $org->id]));
        $this->project = $org->projects()->create(['name' => 'Academic Ecology Jobs', 'website_url' => 'https://academicecologyjobs.com']);
        $this->inbox = $org->emailInboxes()->create(['name' => 'Outreach', 'email_address' => 'owner@example.com', 'from_name' => 'Jonathan', 'is_active' => true, 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'owner@example.com', 'smtp_password' => 'test-password', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'owner@example.com', 'imap_password' => 'test-password']);
        $this->project->emailInboxes()->attach($this->inbox);
        $this->campaign = EmployerCampaign::create(['project_id' => $this->project->id, 'email_inbox_id' => $this->inbox->id, 'audience' => 'Ecology employers', 'offer' => '£100 per listing', 'posting_url' => 'https://academicecologyjobs.com/employer/post-job', 'discover' => false, 'weekly_limit' => 1]);
    }

    private function contact(string $email = 'hiring@university.edu')
    {
        return $this->campaign->contacts()->create(['employer' => 'University', 'email' => $email, 'source_url' => 'https://university.edu/jobs', 'job_advert_url' => 'https://university.edu/jobs', 'job_advert_title' => 'Ecology vacancy', 'relevance' => 'Ecology vacancy', 'status' => 'approved']);
    }

    private function automaticItem()
    {
        $this->campaign->update(['enabled' => true, 'auto_send' => true]);
        $contact = $this->contact();
        $batch = (new EmployerOutreach)->start($this->campaign);
        $thread = Conversation::create(['project_id' => $this->project->id, 'email_inbox_id' => $this->inbox->id, 'contact_email' => $contact->email, 'subject' => 'Ecology recruitment']);
        $message = $thread->messages()->create(['email_inbox_id' => $this->inbox->id, 'direction' => 'outbound', 'status' => 'draft', 'from_email' => 'owner@example.com', 'to_email' => $contact->email, 'subject' => 'Ecology recruitment', 'body' => str_repeat('Relevant ecology recruitment for your department and research team. ', 8).$this->campaign->posting_url, 'message_id' => 'auto-test@example.com']);

        return $batch->items()->create(['employer_contact_id' => $contact->id, 'outreach_message_id' => $message->id, 'status' => 'ready']);
    }

    private function qualityReview(array $overrides = []): array
    {
        return array_merge(['pass' => true, 'advert_open' => true, 'contact_suitable' => true, 'claims_supported' => true, 'personalisation_supported' => true, 'deadline' => now()->addMonth()->toDateString(), 'vacancy_evidence' => 'Ecology vacancy', 'open_evidence' => 'Ecology vacancy', 'contact_evidence' => 'hiring@university.edu', 'reason' => 'Supported by live evidence.'], $overrides);
    }

    public function test_automatic_outreach_queues_only_after_quality_pass_and_is_idempotent(): void
    {
        $item = $this->automaticItem();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn($this->qualityReview());
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->once()->andReturn('Ecology vacancy accepting applications. Contact hiring@university.edu.');
        $job = new AutoSendEmployerEmail($item);
        $job->handle($ai, $pages, new EmployerOutreach);
        $job->handle($ai, $pages, new EmployerOutreach);
        $this->assertSame('passed', $item->fresh()->auto_review_status);
        $this->assertSame('queued', $item->fresh()->message->status);
        Queue::assertPushed(SendReviewedMessage::class, 1);
    }

    public function test_failed_quality_or_imminent_deadline_never_approves(): void
    {
        $item = $this->automaticItem();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn($this->qualityReview(['claims_supported' => false, 'reason' => 'Invented subscriber claim.']));
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->twice()->andReturn('Ecology vacancy hiring@university.edu');
        (new AutoSendEmployerEmail($item))->handle($ai, $pages, new EmployerOutreach);
        $this->assertSame('skipped', $item->fresh()->auto_review_status);
        $this->assertNull($item->fresh()->message->approved_at);
        $item->update(['auto_reviewed_at' => null]);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn($this->qualityReview(['deadline' => now()->toDateString()]));
        (new AutoSendEmployerEmail($item))->handle($ai, $pages, new EmployerOutreach);
        $this->assertStringContainsString('less than two days', $item->fresh()->auto_review_reason);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_missing_published_contact_and_disabled_automation_do_not_send(): void
    {
        $item = $this->automaticItem();
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldNotReceive('json');
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('text')->once()->andReturn('Ecology vacancy with no email address.');
        (new AutoSendEmployerEmail($item))->handle($ai, $pages, new EmployerOutreach);
        $this->assertSame('skipped', $item->fresh()->auto_review_status);
        $item->update(['auto_reviewed_at' => null]);
        $this->campaign->update(['auto_send' => false]);
        (new AutoSendEmployerEmail($item))->handle($ai, $pages, new EmployerOutreach);
        $this->assertNull($item->fresh()->auto_reviewed_at);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_disabling_automation_or_editing_checked_message_blocks_delivery(): void
    {
        $item = $this->automaticItem();
        $item->update(['auto_review_status' => 'passed', 'auto_reviewed_at' => now(), 'auto_review_signature' => AutoSendEmployerEmail::signature($item)]);
        $this->assertTrue((new EmployerOutreach)->approve($item));
        $item->message->update(['body' => 'Changed after checking']);
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldNotReceive('make');
        (new SendReviewedMessage($item->fresh()->message))->handle($factory);
        $this->assertSame('failed', $item->fresh()->message->status);
    }

    public function test_weekly_limit_and_same_vacancy_alias_prevent_extra_approvals(): void
    {
        $first = $this->automaticItem();
        $other = $this->contact('alias@university.edu');
        $other->update(['job_advert_url' => 'https://university.edu/another-job']);
        $message = $first->message->replicate();
        $message->to_email = $other->email;
        $message->message_id = 'other-auto@example.com';
        $message->save();
        $second = $first->batch->items()->create(['employer_contact_id' => $other->id, 'outreach_message_id' => $message->id, 'status' => 'ready']);
        $service = new EmployerOutreach;
        $this->assertTrue($service->approve($first));
        $this->assertFalse($service->approve($second));
        $this->campaign->update(['weekly_limit' => 2]);
        $other->update(['job_advert_url' => $first->contact->job_advert_url]);
        $this->assertFalse($service->approve($second->fresh()));
        $this->assertSame('draft', $second->fresh()->message->status);
        Queue::assertPushed(SendReviewedMessage::class, 1);
    }

    public function test_automatic_mode_can_be_saved_and_is_not_forced_off(): void
    {
        Livewire::test(EmployerOutreachWorkspace::class, ['project' => $this->project])
            ->set('enabled', true)->set('autoSend', true)->call('saveSettings')->assertHasNoErrors();
        $this->assertTrue($this->campaign->fresh()->auto_send);
        $this->assertTrue($this->campaign->fresh()->enabled);
    }

    public function test_weekly_start_is_idempotent_and_does_not_send(): void
    {
        $service = new EmployerOutreach;
        $first = $service->start($this->campaign);
        $second = $service->start($this->campaign);
        $this->assertSame($first->id, $second->id);
        Queue::assertPushed(PrepareEmployerBatch::class, 1);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_daily_batches_are_unique_per_london_day_and_future_preparation_is_delayed(): void
    {
        $this->campaign->update(['cadence' => 'daily']);
        $service = new EmployerOutreach;
        $today = $service->start($this->campaign);
        $this->assertSame($today->id, $service->start($this->campaign)->id);
        $tomorrowDate = now('Europe/London')->addDay()->startOfDay();
        $tomorrow = $service->start($this->campaign, $tomorrowDate);
        $this->assertNotSame($today->id, $tomorrow->id);
        $this->assertSame($tomorrowDate->toDateString(), $tomorrow->week);
        Queue::assertPushed(PrepareEmployerBatch::class, fn ($job) => $job->batch->id === $tomorrow->id && $job->delay->format('H:i') === '09:00');
        Queue::assertPushed(PrepareEmployerBatch::class, 2);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_advert_duplicates_ignore_tracking_parameters_but_preserve_job_identifiers(): void
    {
        $this->assertSame(EmployerOutreach::advertKey('https://www.jobs.ac.uk/job/ABC123/ecology?utm_source=email'), EmployerOutreach::advertKey('http://jobs.ac.uk/job/share?job_ref=ABC123'));
        $this->assertSame(EmployerOutreach::advertKey('https://university.edu/jobs?jobId=12&utm_campaign=ecology#apply'), EmployerOutreach::advertKey('https://university.edu/jobs?jobId=12'));
        $this->assertNotSame(EmployerOutreach::advertKey('https://university.edu/jobs?jobId=12'), EmployerOutreach::advertKey('https://university.edu/jobs?jobId=13'));
    }

    public function test_daily_cap_resets_but_previous_contacts_and_vacancies_are_not_repeated(): void
    {
        $this->campaign->update(['cadence' => 'daily', 'weekly_limit' => 1]);
        $first = $this->automaticItem();
        $service = new EmployerOutreach;
        $this->assertTrue($service->approve($first));
        $first->message->update(['status' => 'sent', 'sent_at' => now(), 'approved_at' => now()]);
        $this->travel(1)->days();
        $this->assertFalse($service->eligible($first->contact));
        $other = $this->contact('new@university.edu');
        $batch = $service->start($this->campaign->fresh());
        $message = $first->message->replicate();
        $message->to_email = $other->email;
        $message->message_id = 'next-day@example.com';
        $message->status = 'draft';
        $message->approved_at = null;
        $message->sent_at = null;
        $message->save();
        $item = $batch->items()->create(['employer_contact_id' => $other->id, 'outreach_message_id' => $message->id, 'status' => 'ready']);
        $this->assertFalse($service->approve($item));
        $other->update(['job_advert_url' => 'https://university.edu/new-vacancy']);
        $this->assertTrue($service->approve($item->fresh()));
    }

    public function test_preparation_caps_batch_and_skips_suppression_and_previous_contacts(): void
    {
        $suppressed = $this->contact('stop@university.edu');
        Suppression::create(['organization_id' => $this->project->organization_id, 'email' => $suppressed->email]);
        $old = $this->contact('old@university.edu');
        $thread = Conversation::create(['project_id' => $this->project->id, 'email_inbox_id' => $this->inbox->id, 'contact_email' => $old->email, 'subject' => 'Previous']);
        $thread->messages()->create(['email_inbox_id' => $this->inbox->id, 'direction' => 'outbound', 'status' => 'sent', 'from_email' => 'owner@example.com', 'to_email' => $old->email, 'subject' => 'Previous', 'body' => 'Hello', 'message_id' => 'previous@example.com']);
        $wanted = $this->contact();
        $this->contact('other@university.edu');
        $batch = (new EmployerOutreach)->start($this->campaign);
        (new PrepareEmployerBatch($batch))->handle(new Assistant, new PublicPage, new EmployerOutreach);
        $this->assertSame(1, $batch->items()->count());
        $this->assertSame($wanted->id, $batch->items()->first()->employer_contact_id);
        Queue::assertPushed(DraftEmployerEmail::class, 1);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_ai_draft_waits_for_approval_and_approval_is_idempotent(): void
    {
        $contact = $this->contact();
        $batch = (new EmployerOutreach)->start($this->campaign);
        $batch->update(['status' => 'drafting']);
        $item = $batch->items()->create(['employer_contact_id' => $contact->id]);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn(['subject' => 'Ecology hiring', 'body' => 'Your ecology recruitment could benefit from a specialist listing.']);
        (new DraftEmployerEmail($item))->handle($ai, new EmployerOutreach);
        $item->refresh();
        $this->assertSame('draft', $item->message->status);
        $this->assertNull($item->message->approved_at);
        Queue::assertNotPushed(SendReviewedMessage::class);
        $this->assertTrue((new EmployerOutreach)->approve($item));
        $this->assertFalse((new EmployerOutreach)->approve($item));
        Queue::assertPushed(SendReviewedMessage::class, 1);
        $this->assertSame('queued', $item->message->fresh()->status);
    }

    public function test_suppression_or_reply_after_drafting_prevents_approval(): void
    {
        $contact = $this->contact();
        $batch = (new EmployerOutreach)->start($this->campaign);
        $batch->update(['status' => 'drafting']);
        $item = $batch->items()->create(['employer_contact_id' => $contact->id]);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->andReturn(['subject' => 'Ecology hiring', 'body' => 'A specialist ecology job board listing.']);
        (new DraftEmployerEmail($item))->handle($ai, new EmployerOutreach);
        Suppression::create(['organization_id' => $this->project->organization_id, 'email' => $contact->email]);
        $this->assertFalse((new EmployerOutreach)->approve($item->fresh()));
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_discovery_only_keeps_emails_found_on_cited_pages(): void
    {
        $this->campaign->update(['discover' => true, 'weekly_limit' => 8]);
        $batch = (new EmployerOutreach)->start($this->campaign);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('request')->andReturn(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Research', 'citations' => [['url' => 'https://university.edu/jobs']]]]]);
        $ai->shouldReceive('text')->andReturn('Research');
        $ai->shouldReceive('json')->andReturn(['contacts' => [
            ['employer' => 'University', 'email' => 'hiring@university.edu', 'source_url' => 'https://university.edu/jobs', 'job_advert_url' => 'https://university.edu/jobs', 'job_advert_title' => 'Ecology vacancy', 'relevance' => 'Ecology role'],
            ['employer' => 'Invented', 'email' => 'guess@university.edu', 'source_url' => 'https://university.edu/jobs', 'job_advert_url' => 'https://university.edu/jobs', 'job_advert_title' => 'Ecology vacancy', 'relevance' => 'Ecology role'],
            ['employer' => 'No evidence', 'email' => 'other@university.edu', 'source_url' => 'https://unknown.edu/jobs', 'relevance' => 'Ecology role'],
            ['employer' => 'No advert', 'email' => 'hiring@university.edu', 'source_url' => 'https://university.edu/jobs', 'relevance' => 'Ecology role'],
            ['employer' => 'Uncited advert', 'email' => 'hiring@university.edu', 'source_url' => 'https://university.edu/jobs', 'job_advert_url' => 'https://university.edu/invented-job', 'job_advert_title' => 'Invented vacancy', 'relevance' => 'Ecology role'],
        ]]);
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('fetch')->with('https://university.edu/jobs')->andReturn('<a href="mailto:hiring@university.edu">Contact</a>');
        (new PrepareEmployerBatch($batch))->handle($ai, $pages, new EmployerOutreach);
        $this->assertSame(1, $this->campaign->contacts()->count());
        $this->assertSame('suggested', $this->campaign->contacts()->first()->status);
        $this->assertSame(1, $batch->items()->count());
        $this->assertSame('https://university.edu/jobs', $this->campaign->contacts()->first()->job_advert_url);
    }

    public function test_missing_job_advert_blocks_preparation_and_approval(): void
    {
        $contact = $this->contact();
        $batch = (new EmployerOutreach)->start($this->campaign);
        $item = $batch->items()->create(['employer_contact_id' => $contact->id]);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn(['subject' => 'Ecology hiring', 'body' => 'An introduction to the ecology job board.']);
        (new DraftEmployerEmail($item))->handle($ai, new EmployerOutreach);
        $contact->update(['job_advert_url' => null]);
        $this->assertFalse((new EmployerOutreach)->approve($item->fresh()));
        $this->assertSame('draft', $item->fresh()->message->status);
        Queue::assertNotPushed(SendReviewedMessage::class);

        $another = $this->contact('another@university.edu');
        $another->update(['job_advert_url' => null]);
        (new PrepareEmployerBatch($batch))->handle(new Assistant, new PublicPage, new EmployerOutreach);
        $this->assertSame(1, $batch->items()->count());
    }

    public function test_job_advert_removed_after_approval_blocks_delivery(): void
    {
        $contact = $this->contact();
        $batch = (new EmployerOutreach)->start($this->campaign);
        $item = $batch->items()->create(['employer_contact_id' => $contact->id]);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn(['subject' => 'Ecology hiring', 'body' => 'An introduction to the ecology job board.']);
        (new DraftEmployerEmail($item))->handle($ai, new EmployerOutreach);
        $this->assertTrue((new EmployerOutreach)->approve($item->fresh()));
        $contact->update(['job_advert_url' => null]);
        $factory = Mockery::mock(DynamicMailerFactory::class);
        $factory->shouldNotReceive('make');
        (new SendReviewedMessage($item->fresh()->message))->handle($factory);
        $this->assertSame('failed', $item->fresh()->message->status);
        $this->assertStringContainsString('missing a job advert link', $item->fresh()->message->error);
    }

    public function test_workspace_renders_and_rejects_other_organization(): void
    {
        $this->get(route('projects.employer-outreach', $this->project))->assertOk()->assertSee('Outreach campaign');
        $other = Organization::create(['name' => 'Other', 'slug' => 'other']);
        $p = $other->projects()->create(['name' => 'Other', 'website_url' => 'https://other.edu']);
        $this->get(route('projects.employer-outreach', $p))->assertNotFound();
        Livewire::test(EmployerOutreachWorkspace::class, ['project' => $this->project])->set('inboxId', 99999)->call('saveSettings')->assertHasErrors('inboxId');
    }

    public function test_batch_review_saves_edits_before_sending(): void
    {
        $contact = $this->contact();
        $batch = (new EmployerOutreach)->start($this->campaign);
        $batch->update(['status' => 'drafting']);
        $item = $batch->items()->create(['employer_contact_id' => $contact->id]);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->andReturn(['subject' => 'Ecology hiring', 'body' => 'An honest introduction to the ecology job board.']);
        (new DraftEmployerEmail($item))->handle($ai, new EmployerOutreach);
        $this->get(route('projects.employer-outreach', $this->project))->assertOk()->assertSee($contact->email);
        Livewire::test(EmployerOutreachWorkspace::class, ['project' => $this->project])
            ->set('subjects.'.$item->id, 'Reviewed subject')
            ->set('bodies.'.$item->id, 'Reviewed personalised employer introduction.')
            ->call('approveBatch', $batch->id)->assertHasNoErrors();
        $this->assertSame('Reviewed subject', $item->fresh()->message->subject);
        $this->assertSame('queued', $item->fresh()->message->status);
        Queue::assertPushed(SendReviewedMessage::class, 1);
    }

    public function test_individual_approval_saves_edits_and_only_queues_the_selected_email_once(): void
    {
        $batch = (new EmployerOutreach)->start($this->campaign);
        $batch->update(['status' => 'drafting']);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->twice()->andReturn(['subject' => 'Ecology hiring', 'body' => 'An introduction to the ecology job board.']);
        $first = $batch->items()->create(['employer_contact_id' => $this->contact()->id]);
        $second = $batch->items()->create(['employer_contact_id' => $this->contact('other@university.edu')->id]);
        foreach ([$first, $second] as $item) {
            (new DraftEmployerEmail($item))->handle($ai, new EmployerOutreach);
        }

        Livewire::test(EmployerOutreachWorkspace::class, ['project' => $this->project])
            ->assertSee('Approve & send this email')
            ->assertSee('Approve batch & send', false)
            ->set('subjects.'.$first->id, 'Personalised subject')
            ->set('bodies.'.$first->id, 'Reviewed individual employer introduction.')
            ->call('approveEmail', $first->id)->assertHasNoErrors()
            ->assertSet('actionItemId', $first->id)
            ->assertSee('Reviewed email queued for sending.')
            ->call('approveEmail', $first->id)->assertHasNoErrors();

        $this->assertSame('Personalised subject', $first->fresh()->message->subject);
        $this->assertSame('Reviewed individual employer introduction.', $first->fresh()->message->body);
        $this->assertSame('queued', $first->fresh()->message->status);
        $this->assertSame('draft', $second->fresh()->message->status);
        Queue::assertPushed(SendReviewedMessage::class, 1);
        Queue::assertPushed(SendReviewedMessage::class, fn ($job) => $job->message->id === $first->fresh()->outreach_message_id);
    }

    public function test_individual_approval_rejects_invalid_edits_and_excluded_contacts(): void
    {
        $batch = (new EmployerOutreach)->start($this->campaign);
        $contact = $this->contact();
        $item = $batch->items()->create(['employer_contact_id' => $contact->id]);
        $ai = Mockery::mock(Assistant::class);
        $ai->shouldReceive('json')->once()->andReturn(['subject' => 'Ecology hiring', 'body' => 'An introduction to the ecology job board.']);
        (new DraftEmployerEmail($item))->handle($ai, new EmployerOutreach);
        Livewire::test(EmployerOutreachWorkspace::class, ['project' => $this->project])
            ->set('subjects.'.$item->id, '')
            ->call('approveEmail', $item->id)->assertHasErrors('subjects.'.$item->id);
        $contact->update(['status' => 'excluded']);
        Livewire::test(EmployerOutreachWorkspace::class, ['project' => $this->project])
            ->call('approveEmail', $item->id)->assertHasNoErrors();
        $this->assertSame('draft', $item->fresh()->message->status);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_expanded_research_verifies_contacts_and_finishes_once_without_sending(): void
    {
        $batch = (new EmployerOutreach)->start($this->campaign);
        $batch->update(['status' => 'researching']);
        Cache::put('employer-research-remaining-'.$batch->id, 1, 86400);
        $ai = Mockery::mock(Assistant::class);
        $data = ['contacts' => [['employer' => 'University', 'email' => 'hiring@university.edu', 'source_url' => 'https://university.edu/jobs', 'job_advert_url' => 'https://university.edu/jobs', 'job_advert_title' => 'Ecology vacancy', 'relevance' => 'Ecology hiring contact; purchasing authority unconfirmed']]];
        $ai->shouldReceive('request')->andReturn(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'citations' => [['url' => 'https://university.edu/jobs']]]]]);
        $ai->shouldReceive('text')->andReturn(json_encode($data));
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('fetch')->with('https://university.edu/jobs')->andReturn('Contact hiring@university.edu');
        $job = new ResearchEmployerContacts($batch, 0, [], 'Ecology employers');
        $job->handle($ai, $pages);
        $job->finish();
        $this->assertSame(1, $batch->items()->count());
        $this->assertSame(0, (int) Cache::get('employer-research-remaining-'.$batch->id));
        Queue::assertPushed(DraftEmployerEmail::class, 1);
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_expanded_preparation_excludes_stale_and_expired_leads_and_preserves_evidence(): void
    {
        $this->campaign->update(['discover' => true, 'weekly_limit' => 50]);
        $lead = ['employer' => 'Current University', 'job_title' => 'Ecology postdoc', 'job_url' => 'https://university.edu/jobs', 'source' => 'jobsacuk', 'signal' => 'Paid channel', 'country' => 'UK', 'deadline' => now()->addMonth(), 'first_seen' => now(), 'rate_url' => 'https://www.jobs.ac.uk/advertise'];
        $this->campaign->leads()->create(array_merge($lead, ['source_job_id' => 'current']));
        $this->campaign->leads()->create(array_merge($lead, ['source_job_id' => 'stale', 'employer' => 'Stale University', 'first_seen' => now()->subDays(61)]));
        $this->campaign->leads()->create(array_merge($lead, ['source_job_id' => 'expired', 'employer' => 'Expired University', 'deadline' => now()->subDay()]));
        $batch = (new EmployerOutreach)->start($this->campaign);
        (new PrepareEmployerBatch($batch))->handle(new Assistant, new PublicPage, new EmployerOutreach);

        Queue::assertPushed(ResearchEmployerContacts::class, 9);
        Queue::assertPushed(ResearchEmployerContacts::class, function ($job) use ($lead) {
            return count($job->leads) === 1
                && $job->leads[0]['employer'] === 'Current University'
                && $job->leads[0]['country'] === 'UK'
                && $job->leads[0]['deadline'] !== null
                && $job->leads[0]['rate_url'] === $lead['rate_url'];
        });
        foreach (Queue::pushed(ResearchEmployerContacts::class) as $queued) {
            foreach ($queued->leads as $selected) {
                $this->assertSame('Current University', $selected['employer']);
            }
        }
        Queue::assertNotPushed(SendReviewedMessage::class);
    }

    public function test_live_feed_import_rejects_stale_and_expired_jobs_and_deduplicates(): void
    {
        config(['outreach.academic_jobs_feed' => 'https://academicecologyjobs.com/feed.json']);
        $item = ['name' => 'Ecology postdoc', 'published' => now()->toIso8601String(), 'attributedTo' => ['name' => 'University', 'location' => 'UK'], 'inReplyTo' => ['href' => 'https://www.jobs.ac.uk/job/example', 'name' => 'View on Jobs.ac.uk'], 'expiresAt' => null];
        $old = $item;
        $old['published'] = now()->subDays(90)->toIso8601String();
        $old['inReplyTo']['href'] .= '-old';
        $expired = $item;
        $expired['expiresAt'] = now()->subDay()->toIso8601String();
        $expired['inReplyTo']['href'] .= '-expired';
        $pages = Mockery::mock(PublicPage::class);
        $pages->shouldReceive('fetch')->andReturn(json_encode(['items' => [$item, $old, $expired]]));
        $this->app->instance(PublicPage::class, $pages);
        $importer = new AcademicEmployerLeads;
        $this->assertSame(1, $importer->import($this->campaign));
        $this->assertSame(0, $importer->import($this->campaign));
        $this->assertSame(100, $this->campaign->leads()->first()->priority);
    }

    public function test_scraper_import_is_read_only_and_ranks_paid_channel_signals(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'academic-jobs-');
        try {
            $db = new PDO('sqlite:'.$file);
            $db->exec('CREATE TABLE job_listings (id INTEGER PRIMARY KEY, institution TEXT, subject_area TEXT, url TEXT, source TEXT, created_at TEXT, valid_through TEXT, source_closed INTEGER)');
            $query = $db->prepare('INSERT INTO job_listings VALUES (?,?,?,?,?,?,?,?)');
            $query->execute([1, 'University', 'Ecology postdoc', 'https://www.jobs.ac.uk/job/test', 'jobsacuk', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 0]);
            $query->execute([2, 'Free university', 'Ecology faculty', 'https://free.edu/job', 'ecoevojobs', now()->toDateTimeString(), null, 0]);
            $query->execute([3, 'Expired', 'Ecology faculty', 'https://closed.edu/job', 'jobsacuk', now()->toDateTimeString(), now()->subDay()->toDateTimeString(), 0]);
            config(['outreach.academic_jobs_database' => $file]);
            $this->assertSame(2, (new AcademicEmployerLeads)->import($this->campaign));
            $this->assertSame(0, (new AcademicEmployerLeads)->import($this->campaign));
            $this->assertSame(100, $this->campaign->leads()->where('source', 'jobsacuk')->first()->priority);
            $this->assertSame(3, (int) $db->query('SELECT COUNT(*) FROM job_listings')->fetchColumn());
        } finally {
            unlink($file);
        }
    }
}
