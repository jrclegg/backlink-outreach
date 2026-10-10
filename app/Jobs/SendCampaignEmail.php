<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Models\Suppression;
use App\Services\Mail\DynamicMailerFactory;
use App\Services\Mail\SmtpErrorTranslator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 30;

    public function __construct(
        public CampaignRecipient $recipient,
        public int $step = 1,
    ) {}

    public function handle(): void
    {
        $recipient = $this->recipient->fresh();

        // Check suppression
        if (Suppression::where('organization_id', $recipient->campaign->project->organization_id)
            ->where('email', $recipient->prospect->contact_email)
            ->exists()) {
            $recipient->update(['status' => 'suppressed']);

            return;
        }

        $campaign = $recipient->campaign;
        $step = $campaign->sequenceSteps()->where('step_number', $this->step)->first();

        if (! $step) {
            return;
        }

        try {
            // Generate message ID for tracking
            if (! $recipient->message_id) {
                $messageId = '<'.$recipient->token.'.'.$campaign->id.'@'.parse_url($campaign->project->website_url, PHP_URL_HOST).'>';
                $recipient->update(['message_id' => $messageId]);
            }

            // Prepare body with token substitution
            $body = $step->body_html;
            $body = str_replace('{{first_name}}', $recipient->prospect->contact_name ?? '', $body);
            $body = str_replace('{{email}}', $recipient->prospect->contact_email, $body);
            $body = str_replace('{{company}}', '', $body);
            $body = str_replace('{{ai_intro}}', $recipient->ai_intro_text ?? '', $body);

            // Add unsubscribe link to body (footer)
            $unsubscribeUrl = route('unsubscribe.show', ['token' => $recipient->token]);
            $body .= "\n\n<p style=\"font-size: 12px; color: #999; margin-top: 40px;\">".
                "<a href=\"{$unsubscribeUrl}\" style=\"color: #999; text-decoration: none;\">Unsubscribe</a>".
                '</p>';

            // Send email
            $factory = new DynamicMailerFactory;
            $mailer = $factory->make($campaign->emailInbox);

            $email = new Email;
            $email->from(new Address($campaign->emailInbox->sender_address, $campaign->emailInbox->from_name))
                ->to($recipient->prospect->contact_email)
                ->subject($step->subject)
                ->html($body)
                ->getHeaders()
                ->addTextHeader('Message-ID', $recipient->message_id);

            $result = $mailer->send($email, DynamicMailerFactory::envelope($campaign->emailInbox, $email));

            \Log::info('Email sent successfully', [
                'recipient' => $recipient->prospect->contact_email,
                'campaign' => $campaign->name,
                'step' => $this->step,
                'message_id' => $recipient->message_id,
            ]);

            // Update recipient
            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
                'current_step' => $this->step,
            ]);

            // Schedule next step if it exists
            $nextStep = $campaign->sequenceSteps()->where('step_number', $this->step + 1)->first();
            if ($nextStep) {
                $recipient->update([
                    'next_step_send_at' => now()->addDays($nextStep->delay_days),
                ]);
            }
        } catch (Exception $e) {
            $sensitiveValues = [$campaign->emailInbox->smtp_password, $campaign->emailInbox->imap_password, $step->subject, $step->body_html, $body ?? ''];
            \Log::warning('Campaign email failed', [
                'recipient_id' => $recipient->id,
                'inbox_id' => $campaign->email_inbox_id,
                'diagnostics' => SmtpErrorTranslator::diagnostics($e, $sensitiveValues),
            ]);
            $recipient->update([
                'status' => 'failed',
                'error' => SmtpErrorTranslator::translate($e).' '.SmtpErrorTranslator::diagnosticSummary($e, $sensitiveValues),
            ]);
            throw $e;
        }
    }
}
