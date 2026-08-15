<?php

namespace App\Services;

use App\Models\CampaignRecipient;
use App\Models\EmailReply;
use App\Models\Prospect;

class ReplyMatcher
{
    public static function matchReply(
        string $fromAddress,
        string $subject,
        string $bodyText,
        string $messageId,
        ?string $inReplyTo,
        string $inboxId,
    ): ?CampaignRecipient {
        // Primary: Try Message-ID/In-Reply-To matching
        if ($inReplyTo) {
            // Extract token from In-Reply-To header (format: <token.campaign_id@domain>)
            if (preg_match('/<([a-f0-9-]+)\./', $inReplyTo, $matches)) {
                $token = $matches[1];
                $recipient = CampaignRecipient::where('token', $token)->first();
                if ($recipient) {
                    return $recipient;
                }
            }
        }

        // Fallback: Match by from_address
        $prospect = Prospect::where('contact_email', $fromAddress)->first();
        if ($prospect) {
            // Find the most recent sent recipient for this prospect
            $recipient = $prospect->campaignRecipients()
                ->where('status', 'sent')
                ->orderBy('sent_at', 'desc')
                ->first();

            if ($recipient) {
                return $recipient;
            }
        }

        return null;
    }

    public static function storeReply(
        ?CampaignRecipient $recipient,
        string $fromAddress,
        string $subject,
        string $bodyText,
        string $messageId,
        ?string $inReplyTo,
        string $inboxId,
    ): EmailReply {
        $reply = EmailReply::create([
            'campaign_recipient_id' => $recipient?->id,
            'email_inbox_id' => $inboxId,
            'from_address' => $fromAddress,
            'subject' => $subject,
            'body_text' => $bodyText,
            'raw_message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'received_at' => now(),
        ]);

        if ($recipient) {
            $recipient->update(['replied_at' => now()]);
        }

        return $reply;
    }
}
