<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Models\ConsultancyProspect;
use App\Models\EmployerBatchItem;
use App\Models\LeadProspect;
use App\Models\OutreachMessage;
use App\Models\SponsorProspect;
use App\Models\Suppression;
use App\Services\Mail\DynamicMailerFactory;
use App\Services\Mail\SmtpErrorTranslator;
use App\Services\Outreach\ConsultancyOutreach;
use App\Services\Outreach\LeadOutreach;
use App\Services\Outreach\PublicPage;
use App\Services\Outreach\SponsorOutreach;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendReviewedMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public bool $failOnTimeout = true;

    public int $timeout = 60;

    public function __construct(public OutreachMessage $message)
    {
        $this->onQueue('outgoing');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('smtp-'.$this->message->email_inbox_id))->releaseAfter(15)->expireAfter(90)];
    }

    public function handle(DynamicMailerFactory $factory): void
    {
        $message = $this->message->fresh();
        if ($message?->status === 'sending' && $message->updated_at->lt(now()->subMinutes(2))) {
            $message->update(['status' => 'failed', 'error' => 'A previous send stopped without confirming delivery. Check the recipient or provider before composing another attempt.']);

            return;
        }
        if (! $message || $message->status !== 'queued' || ! $message->approved_at) {
            return;
        }
        $inbox = $message->emailInbox;
        $sponsor = SponsorProspect::where('outreach_message_id', $message->id)->first();
        if ($sponsor) {
            $campaign = $sponsor->campaign;
            if (! $campaign->enabled || ! $campaign->auto_send || $sponsor->status !== 'passed'
                || ! $sponsor->reviewed_at || $sponsor->reviewed_at->lt(now()->subDay())
                || SponsorOutreach::signature($sponsor) !== $sponsor->signature
                || ! app(SponsorOutreach::class)->eligible($sponsor)) {
                $message->update(['status' => 'failed', 'error' => 'Sponsorship sending stopped: campaign disabled, contact ineligible, quality check expired or draft/evidence changed.']);

                return;
            }
            if (OutreachMessage::whereIn('id', $campaign->prospects()->select('outreach_message_id'))
                ->where('sent_at', '>=', now('Europe/London')->startOfDay())->count() >= $campaign->daily_limit) {
                $this->release((int) max(60, ceil(now('Europe/London')->diffInSeconds(now('Europe/London')->addDay()->startOfDay()))));

                return;
            }
        }
        $consultancy = ConsultancyProspect::where('outreach_message_id', $message->id)->first();
        if ($consultancy && ! app(ConsultancyOutreach::class)->eligible($consultancy)) {
            $message->update(['status' => 'failed', 'error' => 'Consultancy outreach blocked: this firm has already been contacted, excluded or suppressed.']);

            return;
        }
        // 'approved' means the app approved it automatically; drafts you send yourself skip the automatic checks.
        if ($consultancy?->status === 'approved') {
            $campaign = $consultancy->campaign;
            if (! $campaign->enabled || ! $campaign->auto_send || ! $consultancy->reviewed_at || $consultancy->reviewed_at->lt(now()->subDay())
                || ConsultancyOutreach::signature($consultancy) !== $consultancy->signature) {
                $message->update(['status' => 'failed', 'error' => 'Consultancy sending stopped: campaign disabled, quality check expired or draft changed.']);

                return;
            }
            if (OutreachMessage::whereIn('id', $campaign->prospects()->select('outreach_message_id'))
                ->where('sent_at', '>=', now('Europe/London')->startOfDay())->count() >= $campaign->daily_limit) {
                $this->release((int) max(60, ceil(now('Europe/London')->diffInSeconds(now('Europe/London')->addDay()->startOfDay()))));

                return;
            }
        }
        $lead = LeadProspect::where('outreach_message_id', $message->id)->first();
        if ($lead && ! app(LeadOutreach::class)->eligible($lead)) {
            $message->update(['status' => 'failed', 'error' => 'Lead outreach blocked: this business has already been contacted, excluded or suppressed.']);

            return;
        }
        if ($lead?->status === 'approved') {
            $campaign = $lead->campaign;
            if (! $campaign->enabled || ! $campaign->auto_send || ! $lead->reviewed_at || $lead->reviewed_at->lt(now()->subDay())
                || LeadOutreach::signature($lead) !== $lead->signature) {
                $message->update(['status' => 'failed', 'error' => 'Lead sending stopped: campaign disabled, quality check expired or draft changed.']);

                return;
            }
            if (OutreachMessage::whereIn('id', $campaign->prospects()->select('outreach_message_id'))
                ->where('sent_at', '>=', now('Europe/London')->startOfDay())->count() >= $campaign->daily_limit) {
                $this->release((int) max(60, ceil(now('Europe/London')->diffInSeconds(now('Europe/London')->addDay()->startOfDay()))));

                return;
            }
        }
        $employerItem = EmployerBatchItem::with('contact')->where('outreach_message_id', $message->id)->first();
        if ($employerItem?->auto_review_status === 'passed') {
            $campaign = $employerItem->batch->campaign;
            if (! $campaign->enabled || ! $campaign->auto_send || $employerItem->auto_reviewed_at->lt(now()->subDay()) || AutoSendEmployerEmail::signature($employerItem) !== $employerItem->auto_review_signature) {
                $message->update(['status' => 'failed', 'error' => 'Automatic sending stopped: campaign disabled, quality check expired or draft/evidence changed.']);

                return;
            }
            if ($campaign->cadence === 'daily') {
                $campaignIds = EmployerBatchItem::whereHas('batch', fn ($q) => $q->where('employer_campaign_id', $campaign->id))->pluck('outreach_message_id');
                if (OutreachMessage::whereIn('id', $campaignIds)->where('sent_at', '>=', now('Europe/London')->startOfDay())->count() >= $campaign->weekly_limit) {
                    $this->release((int) max(60, ceil(now('Europe/London')->diffInSeconds(now('Europe/London')->addDay()->startOfDay()))));

                    return;
                }
            }
        }
        if ($employerItem && ($employerItem->contact->status === 'excluded' || ! PublicPage::validUrl($employerItem->contact->job_advert_url ?? ''))) {
            $message->update(['status' => 'failed', 'error' => 'Employer outreach blocked: contact excluded or missing a job advert link.']);

            return;
        }
        if (! $inbox->is_active || Suppression::where('organization_id', $inbox->organization_id)->where('email', strtolower($message->to_email))->exists()) {
            $message->update(['status' => 'failed', 'error' => 'Sending blocked: inactive account or suppressed recipient.']);

            return;
        }
        $sent = OutreachMessage::where('email_inbox_id', $inbox->id)->where('sent_at', '>=', now()->subHour())->count();
        $sent += CampaignRecipient::whereHas('campaign', fn ($q) => $q->where('email_inbox_id', $inbox->id))->where('sent_at', '>=', now()->subHour())->count();
        if ($sent >= max(1, $inbox->max_sends_per_hour)) {
            $this->release(60);

            return;
        }
        if (! OutreachMessage::whereKey($message->id)->where('status', 'queued')->update(['status' => 'sending'])) {
            return;
        }
        try {
            $email = (new Email)->from(new Address($inbox->sender_address, $inbox->from_name))
                ->to($message->to_email)->subject($message->subject)->text($message->body);
            $email->getHeaders()->addIdHeader('Message-ID', $message->message_id);
            if ($message->in_reply_to) {
                $email->getHeaders()->addIdHeader('In-Reply-To', trim($message->in_reply_to, '<> '));
            }
            if ($message->references) {
                $email->getHeaders()->addIdHeader('References', preg_split('/[\s<>,]+/', $message->references, -1, PREG_SPLIT_NO_EMPTY));
            }
            $factory->make($inbox)->send($email, DynamicMailerFactory::envelope($inbox, $email));
            $message->update(['from_email' => $inbox->sender_address, 'status' => 'sent', 'sent_at' => now(), 'error' => null]);
            $message->conversation->update(['last_message_at' => now()]);
            if ($message->conversation->opportunity?->status === 'new') {
                $message->conversation->opportunity->update(['status' => 'contacted']);
            }
        } catch (\Throwable $e) {
            $sensitiveValues = [$inbox->smtp_password, $inbox->imap_password, $message->subject, $message->body];
            // SMTP delivery can be ambiguous. Never automatically resend after an error.
            $message->update(['status' => 'failed', 'error' => SmtpErrorTranslator::translate($e).' '.SmtpErrorTranslator::diagnosticSummary($e, $sensitiveValues).' Delivery could not be confirmed. Check the recipient’s inbox and your provider’s Sent folder before creating another draft. This email will not be retried automatically.']);
            Log::warning('Outgoing email failed', [
                'message_id' => $message->id,
                'inbox_id' => $inbox->id,
                'diagnostics' => SmtpErrorTranslator::diagnostics($e, $sensitiveValues),
                'provider' => $inbox->provider,
                'uses_alias' => (bool) $inbox->from_address,
            ]);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $this->message->update(['status' => 'failed', 'error' => 'The send worker stopped. Check your provider’s Sent folder before retrying.']);
    }
}
