<?php

namespace App\Livewire\Inboxes;

use App\Jobs\PollInboxForReplies;
use App\Models\Conversation;
use App\Models\EmailInbox;
use App\Services\Mail\MailConnectionVerifier;
use App\Services\Mail\MailProviderDetector;
use App\Services\Mail\ProviderSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class InboxForm extends Component
{
    #[Locked]
    public ?int $inboxId = null;

    #[Locked]
    public string $step = 'email';

    #[Locked]
    public string $identifiedEmail = '';

    public bool $manualMode = false;

    public string $provider = 'custom';

    public string $name = '';

    public string $email_address = '';

    public string $from_name = '';

    public string $from_address = '';

    public string $password = '';

    public string $smtp_host = '';

    public int $smtp_port = 587;

    public string $smtp_encryption = 'tls';

    public string $smtp_username = '';

    public string $imap_host = '';

    public int $imap_port = 993;

    public string $imap_encryption = 'ssl';

    public string $imap_username = '';

    public string $imap_password = '';

    public string $imap_folder = 'INBOX';

    public array $projectIds = [];

    public ?int $defaultProjectId = null;

    public int $max_sends_per_hour = 20;

    public bool $is_active = true;

    public string $notice = '';

    public function mount(?int $id = null): void
    {
        $this->from_name = Auth::user()->name;
        if (! $id) {
            return;
        }
        $inbox = Auth::user()->currentOrganization->emailInboxes()->findOrFail($id);
        $this->inboxId = $id;
        foreach (['provider', 'name', 'email_address', 'from_name', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_folder', 'max_sends_per_hour'] as $field) {
            $this->$field = $inbox->$field;
        }
        $this->from_address = $inbox->from_address ?? '';
        $this->is_active = (bool) $inbox->is_active;
        $this->projectIds = $inbox->projects()->pluck('projects.id')->toArray();
        $this->defaultProjectId = $inbox->default_project_id;
        $this->step = 'credentials';
        $this->identifiedEmail = strtolower($inbox->email_address);
        $preset = ProviderSettings::all()[$this->provider] ?? null;
        $this->manualMode = ! $preset || $this->provider === 'custom' || $preset['smtp'] !== $inbox->smtp_host || $preset['imap'] !== $inbox->imap_host || $inbox->smtp_port !== 587 || $inbox->smtp_encryption !== 'tls' || $inbox->imap_port !== 993 || $inbox->imap_encryption !== 'ssl';
    }

    public function identify(): void
    {
        $this->email_address = strtolower(trim($this->email_address));
        $this->validate(['email_address' => 'required|email|max:255']);
        $this->resetValidation();
        $this->password = '';
        $this->imap_password = '';
        $this->provider = app(MailProviderDetector::class)->detect($this->email_address) ?? 'custom';
        $this->identifiedEmail = $this->email_address;
        $this->manualMode = false;
        $this->updatedProvider();
        $this->manualMode = false;
        $this->step = 'credentials';
        $this->name = $this->email_address;
        $this->smtp_username = $this->email_address;
        $this->imap_username = $this->email_address;
        $projects = Auth::user()->currentOrganization->projects;
        $domain = substr(strrchr($this->email_address, '@'), 1);
        $matches = $projects->filter(fn ($p) => preg_replace('/^www\./', '', strtolower(parse_url($p->website_url, PHP_URL_HOST) ?? '')) === $domain);
        $project = $matches->count() === 1 ? $matches->first() : ($projects->count() === 1 ? $projects->first() : null);
        $this->projectIds = $project ? [$project->id] : [];
        $this->defaultProjectId = $project?->id;
    }

    public function updatedEmailAddress(): void
    {
        if ($this->inboxId) {
            return;
        }
        $this->step = 'email';
        $this->identifiedEmail = '';
        $this->password = '';
        $this->imap_password = '';
    }

    public function changeEmail(): void
    {
        if ($this->inboxId) {
            return;
        }
        $this->updatedEmailAddress();
        $this->resetValidation();
    }

    public function updatedProjectIds(): void
    {
        if (count($this->projectIds) === 1) {
            $this->defaultProjectId = (int) $this->projectIds[0];
        } elseif (! in_array($this->defaultProjectId, $this->projectIds)) {
            $this->defaultProjectId = null;
        }
    }

    public function updatedProvider(): void
    {
        $this->resetValidation();
        $config = ProviderSettings::all()[$this->provider] ?? ProviderSettings::all()['custom'];
        $this->manualMode = $this->provider === 'custom';
        $this->smtp_host = $config['smtp'];
        $this->imap_host = $config['imap'];
        $this->smtp_port = 587;
        $this->smtp_encryption = 'tls';
        $this->imap_port = 993;
        $this->imap_encryption = 'ssl';
    }

    private function values(): array
    {
        if ($this->step !== 'credentials' || strtolower(trim($this->email_address)) !== $this->identifiedEmail) {
            throw ValidationException::withMessages(['email_address' => 'Enter your email and continue before connecting.']);
        }
        if ($this->provider === 'custom' && ! $this->manualMode) {
            throw ValidationException::withMessages(['provider' => 'Choose who provides your email so we can set it up for you.']);
        }
        if (! $this->manualMode) {
            $preset = ProviderSettings::all()[$this->provider] ?? null;
            if (! $preset) {
                throw ValidationException::withMessages(['provider' => 'Choose an email provider.']);
            }
            $this->smtp_host = $preset['smtp'];
            $this->imap_host = $preset['imap'];
            $this->smtp_port = 587;
            $this->smtp_encryption = 'tls';
            $this->imap_port = 993;
            $this->imap_encryption = 'ssl';
        }
        $this->from_address = strtolower(trim($this->from_address));
        $this->name = trim($this->name) ?: $this->email_address;
        $this->from_name = trim($this->from_name) ?: Auth::user()->name;
        $this->validate([
            'provider' => 'required|in:'.implode(',', array_keys(ProviderSettings::all())),
            'name' => 'required|string|max:255', 'email_address' => 'required|email|max:255', 'from_name' => 'required|string|max:255',
            'from_address' => 'nullable|email|max:255',
            'password' => ($this->inboxId ? 'nullable' : 'required').'|string|max:1000',
            'smtp_host' => 'required|regex:/^[a-zA-Z0-9.-]+$/|max:255', 'smtp_port' => 'required|integer|in:465,587',
            'smtp_encryption' => 'required|in:ssl,tls', 'smtp_username' => 'nullable|string|max:255',
            'imap_host' => 'required|regex:/^[a-zA-Z0-9.-]+$/|max:255', 'imap_port' => 'required|integer|in:993,143',
            'imap_encryption' => 'required|in:ssl,tls', 'imap_username' => 'nullable|string|max:255', 'imap_password' => 'nullable|string|max:1000',
            'imap_folder' => 'required|string|max:255', 'max_sends_per_hour' => 'required|integer|min:1|max:200',
            'projectIds' => 'array', 'projectIds.*' => 'integer', 'defaultProjectId' => 'nullable|integer',
        ]);
        $org = Auth::user()->currentOrganization;
        $owned = $org->projects()->whereIn('id', $this->projectIds)->pluck('id')->toArray();
        abort_unless(count(array_unique($this->projectIds)) === count($owned), 403);
        if ($this->defaultProjectId && ! in_array($this->defaultProjectId, $owned)) {
            throw ValidationException::withMessages(['defaultProjectId' => 'Choose one of the selected projects.']);
        }
        $existing = $this->inboxId ? $org->emailInboxes()->findOrFail($this->inboxId) : null;
        if ($existing && strtolower($existing->email_address) !== strtolower($this->email_address)) {
            throw ValidationException::withMessages(['email_address' => 'Connect a separate account to use a different email address.']);
        }
        if ($org->emailInboxes()->whereRaw('lower(email_address) = ?', [strtolower($this->email_address)])->when($this->inboxId, fn ($q) => $q->where('id', '!=', $this->inboxId))->exists()) {
            throw ValidationException::withMessages(['email_address' => 'This email account is already connected. Edit it from Email accounts.']);
        }

        return [
            'from_address' => $this->from_address ?: null,
            'provider' => $this->provider, 'name' => $this->name, 'email_address' => strtolower($this->email_address), 'from_name' => $this->from_name,
            'smtp_host' => $this->smtp_host, 'smtp_port' => $this->smtp_port, 'smtp_encryption' => $this->smtp_encryption,
            'smtp_username' => $this->smtp_username ?: $this->email_address, 'smtp_password' => $this->password ?: $existing?->smtp_password,
            'imap_host' => $this->imap_host, 'imap_port' => $this->imap_port, 'imap_encryption' => $this->imap_encryption,
            'imap_username' => $this->imap_username ?: $this->email_address, 'imap_password' => $this->imap_password ?: ($this->password ?: $existing?->imap_password),
            'imap_folder' => $this->imap_folder, 'max_sends_per_hour' => $this->max_sends_per_hour, 'is_active' => $this->is_active,
            'default_project_id' => $this->defaultProjectId,
        ];
    }

    public function testConnection(): void
    {
        $values = $this->values();
        app(MailConnectionVerifier::class)->verify(new EmailInbox($values));
        $this->notice = 'Sending and receiving both work. No email was sent.';
    }

    public function save(): void
    {
        $values = $this->values();
        $org = Auth::user()->currentOrganization;
        $existing = $this->inboxId ? $org->emailInboxes()->findOrFail($this->inboxId) : null;
        $connectionFields = ['email_address', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder'];
        $changed = ! $existing;
        foreach ($connectionFields as $field) {
            if ($existing && (string) $existing->$field !== (string) $values[$field]) {
                $changed = true;
            }
        }
        if ($changed) {
            app(MailConnectionVerifier::class)->verify(new EmailInbox($values));
            $values['sync_error'] = null;
        }
        $inbox = \DB::transaction(function () use ($values, $org) {
            $inbox = $this->inboxId ? $org->emailInboxes()->findOrFail($this->inboxId) : $org->emailInboxes()->make();
            // Changing account identity must not merge two mailboxes' threads or message IDs.
            if ($inbox->exists && strtolower($inbox->email_address) !== strtolower($values['email_address']) && Conversation::where('email_inbox_id', $inbox->id)->exists()) {
                throw ValidationException::withMessages(['email_address' => 'Add a separate account to change the email address of a mailbox with conversations.']);
            }
            $inbox->fill($values);
            $inbox->save();
            $inbox->projects()->sync($this->projectIds);

            return $inbox;
        });
        $this->password = '';
        $this->imap_password = '';
        if ($changed && $inbox->is_active) {
            PollInboxForReplies::dispatch($inbox);
        }
        session()->flash('sync', $changed ? 'Account connected. Sending and receiving verified; your first inbox sync is queued.' : 'Account updated.');
        $this->redirectRoute('inboxes.index');
    }

    public function render()
    {
        return view('livewire.inboxes.inbox-form', ['providers' => ProviderSettings::all(), 'projects' => Auth::user()->currentOrganization->projects, 'providerHelp' => ProviderSettings::help($this->provider)])->layout('components.layouts.app', ['title' => $this->inboxId ? 'Edit email account' : 'Connect email account']);
    }
}
