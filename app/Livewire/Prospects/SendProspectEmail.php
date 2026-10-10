<?php

namespace App\Livewire\Prospects;

use App\Jobs\SendReviewedMessage;
use App\Models\Conversation;
use App\Models\EmailInbox;
use App\Models\Prospect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SendProspectEmail extends Component
{
    public Prospect $prospect;

    public int $projectId;

    #[Validate('required|email')]
    public string $toEmail = '';

    #[Validate('required|string|max:255')]
    public string $subject = '';

    #[Validate('required|string|min:10')]
    public string $body = '';

    #[Validate('required|exists:email_inboxes,id')]
    public ?int $inboxId = null;

    public bool $sending = false;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(Prospect $prospect)
    {
        abort_unless($prospect->project->organization_id === Auth::user()->current_organization_id, 404);
        $this->prospect = $prospect;
        $this->projectId = $prospect->project_id;
        $this->toEmail = $prospect->contact_email ?? '';
    }

    public function send()
    {
        $this->validate();

        try {
            $this->sending = true;
            $this->errorMessage = null;
            $this->successMessage = null;

            $org = Auth::user()->currentOrganization;
            $inbox = EmailInbox::find($this->inboxId);

            if (! $inbox || ! $org->emailInboxes->contains($inbox)) {
                $this->errorMessage = 'Invalid inbox selected';
                $this->sending = false;

                return;
            }

            abort_unless($this->prospect->project->organization_id === $org->id, 404);
            $thread = Conversation::create([
                'project_id' => $this->prospect->project_id, 'email_inbox_id' => $inbox->id,
                'contact_email' => strtolower($this->toEmail), 'subject' => $this->subject,
            ]);
            $message = $thread->messages()->create([
                'email_inbox_id' => $inbox->id, 'direction' => 'outbound', 'status' => 'queued',
                'from_email' => $inbox->sender_address, 'to_email' => strtolower($this->toEmail),
                'subject' => $this->subject, 'body' => $this->body,
                'message_id' => Str::uuid().'@'.parse_url($this->prospect->project->website_url, PHP_URL_HOST),
                'approved_at' => now(),
            ]);
            $this->prospect->project->emailInboxes()->syncWithoutDetaching([$inbox->id]);
            SendReviewedMessage::dispatch($message);

            $this->sending = false;
            $this->successMessage = "Reviewed email queued for {$this->toEmail}. Follow the conversation in the project inbox.";
            $this->body = '';
            $this->subject = '';

        } catch (\Exception $e) {
            $this->sending = false;
            $this->errorMessage = 'Failed to send email: '.$e->getMessage();
            Log::error('Email send error', [
                'prospect_id' => $this->prospect->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $inboxes = $org->emailInboxes;

        return view('livewire.prospects.send-prospect-email', [
            'inboxes' => $inboxes,
        ])->layout('components.layouts.app', ['title' => 'Send Email']);
    }
}
