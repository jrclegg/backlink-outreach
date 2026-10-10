<?php

namespace Tests\Feature;

use App\Jobs\PollInboxForReplies;
use App\Livewire\Inboxes\InboxForm;
use App\Models\EmailInbox;
use App\Models\Organization;
use App\Models\User;
use App\Services\Mail\DynamicMailerFactory;
use App\Services\Mail\ImapGateway;
use App\Services\Mail\MailConnectionVerifier;
use App\Services\Mail\MailProviderDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class EmailConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        Queue::fake();
        $this->org = Organization::create(['name' => 'Sites', 'slug' => 'sites']);
        $this->actingAs(User::factory()->create(['name' => 'Jonathan', 'current_organization_id' => $this->org->id]));
    }

    private function detect(?string $provider): void
    {
        $this->mock(MailProviderDetector::class, fn ($m) => $m->shouldReceive('detect')->andReturn($provider));
    }

    public function test_first_screen_only_needs_email(): void
    {
        Livewire::test(InboxForm::class)->assertSee('What’s your email address?')->assertDontSee('SMTP host')->assertDontSee('Mailbox password')->assertDontSee('Email provider');
    }

    public function test_email_detection_fills_settings_and_matching_project_automatically(): void
    {
        $this->detect('ionos_uk');
        $project = $this->org->projects()->create(['name' => 'Coworking', 'website_url' => 'https://coworkingspacehub.com']);
        $this->org->projects()->create(['name' => 'Other', 'website_url' => 'https://example.com']);
        Livewire::test(InboxForm::class)->set('email_address', 'hello@coworkingspacehub.com')->call('identify')
            ->assertSet('provider', 'ionos_uk')->assertSet('smtp_host', 'smtp.ionos.co.uk')->assertSet('imap_host', 'imap.ionos.co.uk')
            ->assertSet('projectIds', [$project->id])->assertSet('from_name', 'Jonathan')->assertDontSee('SMTP host')->assertSee('Connect account');
    }

    public function test_connect_verifies_both_protocols_saves_and_queues_initial_sync(): void
    {
        $this->detect('gmail');
        $this->mock(MailConnectionVerifier::class, function ($m) {
            $m->shouldReceive('verify')->once()->withArgs(fn ($inbox) => $inbox->smtp_host === 'smtp.gmail.com' && $inbox->imap_host === 'imap.gmail.com' && $inbox->smtp_password === 'app-secret');
        });
        Livewire::test(InboxForm::class)->set('email_address', 'hello@gmail.com')->call('identify')->set('password', 'app-secret')->call('save')->assertHasNoErrors()->assertRedirect(route('inboxes.index'));
        $inbox = EmailInbox::firstOrFail();
        $this->assertSame('hello@gmail.com', $inbox->name);
        $this->assertSame('Jonathan', $inbox->from_name);
        $this->assertSame('app-secret', $inbox->imap_password);
        Queue::assertPushed(PollInboxForReplies::class, 1);
    }

    public function test_yahoo_alias_is_saved_without_changing_login_and_can_be_removed(): void
    {
        $this->detect('yahoo');
        $this->mock(MailConnectionVerifier::class, fn ($m) => $m->shouldReceive('verify')->once()->withArgs(
            fn ($i) => $i->smtp_username === 'owner@yahoo.com' && $i->imap_username === 'owner@yahoo.com'
                && $i->sender_address === 'hello@academicecologyjobs.com'
        ));
        Livewire::test(InboxForm::class)->set('email_address', 'owner@yahoo.com')->call('identify')
            ->set('password', 'secret')->set('from_address', ' Hello@AcademicEcologyJobs.com ')->call('save')->assertHasNoErrors();
        $inbox = EmailInbox::firstOrFail();
        Livewire::test(InboxForm::class, ['id' => $inbox->id])->assertSet('from_address', 'hello@academicecologyjobs.com')
            ->set('from_address', 'invalid-address')->call('save')->assertHasErrors('from_address')
            ->set('from_address', '')->call('save')->assertHasNoErrors();
        $this->assertSame('owner@yahoo.com', $inbox->fresh()->sender_address);
        $this->assertSame('secret', $inbox->fresh()->smtp_password);
    }

    public function test_failed_verification_does_not_save_or_sync(): void
    {
        $this->detect('yahoo');
        $this->mock(MailConnectionVerifier::class, fn ($m) => $m->shouldReceive('verify')->once()->andThrow(ValidationException::withMessages(['connection' => 'Use a Yahoo app password.'])));
        Livewire::test(InboxForm::class)->set('email_address', 'hello@yahoo.com')->call('identify')->set('password', 'wrong')->call('save')->assertHasErrors('connection');
        $this->assertDatabaseCount('email_inboxes', 0);
        Queue::assertNothingPushed();
    }

    public function test_changing_email_invalidates_detected_provider_and_clears_password(): void
    {
        $this->detect('gmail');
        $this->mock(MailConnectionVerifier::class, fn ($m) => $m->shouldNotReceive('verify'));
        Livewire::test(InboxForm::class)->set('email_address', 'a@gmail.com')->call('identify')->set('password', 'secret')->set('email_address', 'b@yahoo.com')->assertSet('step', 'email')->assertSet('password', '')->call('save')->assertHasErrors('email_address');
        $this->assertDatabaseCount('email_inboxes', 0);
    }

    public function test_unknown_provider_gets_a_simple_choice_without_guessing_servers(): void
    {
        $this->detect(null);
        Livewire::test(InboxForm::class)->set('email_address', 'hello@custom.test')->call('identify')->assertSet('smtp_host', '')->assertSee('Who do you sign in with?')->assertDontSee('SMTP host')->set('provider', 'zoho_eu')->assertSet('smtp_host', 'smtppro.zoho.eu')->assertSee('Connect account');
    }

    public function test_connection_verification_opens_inbox_without_listing_folders(): void
    {
        $transport = Mockery::mock(EsmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();
        $this->mock(DynamicMailerFactory::class, fn ($m) => $m->shouldReceive('transport')->andReturn($transport));
        $client = Mockery::mock();
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('openFolder')->once()->with('INBOX')->andReturn([]);
        $client->shouldNotReceive('getFolderByPath');
        $client->shouldReceive('disconnect')->once();
        $this->mock(ImapGateway::class, fn ($m) => $m->shouldReceive('client')->andReturn($client));
        (new MailConnectionVerifier)->verify(new EmailInbox(['provider' => 'yahoo', 'imap_folder' => 'INBOX']));
        $this->addToAssertionCount(1);
    }

    public function test_connection_failure_explains_receiving_and_safely_closes_clients(): void
    {
        $transport = Mockery::mock(EsmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();
        $this->mock(DynamicMailerFactory::class, fn ($m) => $m->shouldReceive('transport')->andReturn($transport));
        $client = Mockery::mock();
        $client->shouldReceive('connect')->andThrow(new \RuntimeException('secret-password in raw provider error'));
        $client->shouldReceive('disconnect')->once();
        $this->mock(ImapGateway::class, fn ($m) => $m->shouldReceive('client')->andReturn($client));
        try {
            (new MailConnectionVerifier)->verify(new EmailInbox(['provider' => 'gmail']));
            $this->fail('Expected failure');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('receiving', $e->getMessage());
            $this->assertStringNotContainsString('secret-password', $e->getMessage());
        }
    }

    public function test_automatic_mode_ignores_tampered_server_fields(): void
    {
        $this->detect('gmail');
        $this->mock(MailConnectionVerifier::class, fn ($m) => $m->shouldReceive('verify')->once()->withArgs(fn ($i) => $i->smtp_host === 'smtp.gmail.com'));
        Livewire::test(InboxForm::class)->set('email_address', 'hello@gmail.com')->call('identify')->set('password', 'secret')->set('smtp_host','attacker.example')->call('save')->assertHasNoErrors();
        $this->assertSame('smtp.gmail.com',EmailInbox::first()->smtp_host);
    }
}
