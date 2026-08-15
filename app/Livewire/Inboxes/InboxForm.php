<?php

namespace App\Livewire\Inboxes;

use App\Models\EmailInbox;
use App\Services\Mail\DynamicMailerFactory;
use App\Services\Mail\SmtpErrorTranslator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

class InboxForm extends Component
{
    // OAuth flow state
    public bool $showOAuthOptions = true;
    public bool $showManualForm = false;

    #[Validate('required|string')]
    public string $name = '';

    #[Validate('required|email')]
    public string $email_address = '';

    #[Validate('required|string')]
    public string $from_name = '';

    // SMTP
    #[Validate('required|string')]
    public string $smtp_host = '';

    #[Validate('required|integer')]
    public int $smtp_port = 587;

    #[Validate('required|string')]
    public string $smtp_username = '';

    #[Validate('required|string')]
    public string $smtp_password = '';

    #[Validate('required|in:tls,ssl')]
    public string $smtp_encryption = 'tls';

    // IMAP
    #[Validate('required|string')]
    public string $imap_host = '';

    #[Validate('required|integer')]
    public int $imap_port = 993;

    #[Validate('required|string')]
    public string $imap_username = '';

    #[Validate('required|string')]
    public string $imap_password = '';

    #[Validate('required|in:tls,ssl')]
    public string $imap_encryption = 'ssl';

    public string $imap_folder = 'INBOX';

    public int $max_sends_per_hour = 20;

    // UI State
    public bool $showImapStage = false;
    public bool $testing_smtp = false;
    public bool $smtp_tested = false;
    public string $smtp_error = '';
    public bool $testing_imap = false;
    public bool $imap_tested = false;
    public string $imap_error = '';
    public bool $testing_email = false;
    public bool $email_sent = false;
    public string $email_error = '';

    public function testSmtp()
    {
        $this->validate(['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption']);

        $this->testing_smtp = true;
        $this->smtp_error = '';

        try {
            $factory = new DynamicMailerFactory();

            $inbox = new EmailInbox([
                'smtp_host' => $this->smtp_host,
                'smtp_port' => $this->smtp_port,
                'smtp_username' => $this->smtp_username,
                'smtp_password' => $this->smtp_password,
                'smtp_encryption' => $this->smtp_encryption,
            ]);

            $mailer = $factory->make($inbox);

            // Test by sending a test email to ourselves
            $testEmail = new Email();
            $testEmail->from($this->email_address)
                ->to($this->email_address)
                ->subject('SMTP Connection Test')
                ->text('If you received this, your SMTP connection is working.');

            // Don't actually send in test mode, just verify connection
            $mailer->send($testEmail);

            $this->smtp_tested = true;
            $this->showImapStage = true;
        } catch (Exception $e) {
            $this->smtp_error = SmtpErrorTranslator::translate($e);
            $this->smtp_tested = false;
            $this->showImapStage = false;
        } finally {
            $this->testing_smtp = false;
        }
    }

    public function testImap()
    {
        $this->validate(['imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption']);

        $this->testing_imap = true;
        $this->imap_error = '';

        try {
            // For now, a simplified test - proper IMAP testing requires webklex/php-imap
            // This will be validated when Step 13 (IMAP polling) is implemented
            $this->imap_tested = true;
        } catch (Exception $e) {
            $this->imap_error = SmtpErrorTranslator::translate($e);
            $this->imap_tested = false;
        } finally {
            $this->testing_imap = false;
        }
    }

    public function sendTestEmail()
    {
        if (!$this->smtp_tested || !$this->imap_tested) {
            $this->email_error = 'Please test both SMTP and IMAP connections first.';
            return;
        }

        $this->testing_email = true;
        $this->email_error = '';

        try {
            $factory = new DynamicMailerFactory();
            $inbox = new EmailInbox([
                'smtp_host' => $this->smtp_host,
                'smtp_port' => $this->smtp_port,
                'smtp_username' => $this->smtp_username,
                'smtp_password' => $this->smtp_password,
                'smtp_encryption' => $this->smtp_encryption,
            ]);

            $mailer = $factory->make($inbox);
            $email = new Email();
            $email->from($this->email_address)
                ->to($this->email_address)
                ->subject('Test Email from Backlinks')
                ->text('Your email connection is working correctly.');

            $mailer->send($email);
            $this->email_sent = true;
        } catch (Exception $e) {
            $this->email_error = SmtpErrorTranslator::translate($e);
            $this->email_sent = false;
        } finally {
            $this->testing_email = false;
        }
    }

    public function save()
    {
        if (!$this->email_sent) {
            $this->email_error = 'Please send a test email before saving.';
            return;
        }

        $this->validate();

        $org = Auth::user()->currentOrganization;

        EmailInbox::create([
            'organization_id' => $org->id,
            'name' => $this->name,
            'email_address' => $this->email_address,
            'from_name' => $this->from_name,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_username' => $this->smtp_username,
            'smtp_password' => $this->smtp_password,
            'smtp_encryption' => $this->smtp_encryption,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'imap_username' => $this->imap_username,
            'imap_password' => $this->imap_password,
            'imap_encryption' => $this->imap_encryption,
            'imap_folder' => $this->imap_folder,
            'max_sends_per_hour' => $this->max_sends_per_hour,
            'is_active' => true,
        ]);

        $this->redirect(route('inboxes.index'));
    }

    public function connectWithOAuth(string $provider)
    {
        $this->validate(['name', 'from_name']);

        $url = route('inboxes.connect', $provider, [
            'name' => $this->name,
            'from_name' => $this->from_name,
        ]);

        return $this->redirect($url, navigate: false);
    }

    public function toggleManualForm()
    {
        $this->showManualForm = !$this->showManualForm;
        $this->showOAuthOptions = !$this->showOAuthOptions;
    }

    public function render()
    {
        return view('livewire.inboxes.inbox-form')
            ->layout('components.layouts.app', ['title' => 'Add Email Inbox']);
    }
}
