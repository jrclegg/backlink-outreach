<?php

namespace App\Livewire\Prospects;

use App\Models\Prospect;
use App\Models\EmailInbox;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Validate;

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

            if (!$inbox || !$org->emailInboxes->contains($inbox)) {
                $this->errorMessage = 'Invalid inbox selected';
                $this->sending = false;
                return;
            }

            // Create or get a campaign for individual outreach
            $campaign = Campaign::firstOrCreate(
                [
                    'project_id' => $this->projectId,
                    'name' => 'Individual Outreach',
                    'status' => 'active',
                ],
                [
                    'template_id' => null,
                    'status' => 'active',
                ]
            );

            // Create campaign recipient to track this email
            $recipient = CampaignRecipient::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'prospect_id' => $this->prospect->id,
                ],
                [
                    'status' => 'pending',
                    'token' => Str::uuid(),
                ]
            );

            // Send the email
            Mail::mailer(config('mail.default'))->raw($this->body, function ($message) use ($inbox) {
                $message->to($this->toEmail)
                    ->subject($this->subject)
                    ->from($inbox->email_address, $inbox->name ?? 'Outreach');
            });

            // Mark as sent
            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->sending = false;
            $this->successMessage = "Email sent to {$this->toEmail}";
            $this->body = '';
            $this->subject = '';

        } catch (\Exception $e) {
            $this->sending = false;
            $this->errorMessage = 'Failed to send email: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error('Email send error', [
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
