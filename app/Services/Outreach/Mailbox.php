<?php

namespace App\Services\Outreach;

use App\Models\CampaignRecipient;
use App\Models\Conversation;
use App\Models\EmailInbox;
use App\Models\OutreachMessage;
use App\Services\ReplyMatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Mailbox
{
    public function ingest(EmailInbox $inbox, array $data): ?OutreachMessage
    {
        if (! filter_var($data['from'], FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $id = trim($data['message_id'] ?? '', "<> \t\r\n");
        if (! $id) {
            $id = hash('sha256', json_encode(array_intersect_key($data, array_flip(['from', 'to', 'subject', 'body'])))).'@import.local';
        }

        return DB::transaction(function () use ($inbox, $data, $id) {
            $existing = OutreachMessage::where('email_inbox_id', $inbox->id)->where('message_id', $id)->first();
            if ($existing) {
                return $existing;
            }
            $references = preg_split('/[\s<>,]+/', ($data['in_reply_to'] ?? '').' '.($data['references'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $parent = OutreachMessage::where('email_inbox_id', $inbox->id)->whereIn('message_id', $references)->latest('id')->first();
            $recipient = null;
            $thread = $parent?->conversation;
            if (! $thread) {
                $recipient = CampaignRecipient::whereHas('campaign', fn ($q) => $q->where('email_inbox_id', $inbox->id))
                    ->where(function ($q) use ($references) {
                        $q->whereIn('message_id', $references)->orWhereIn('message_id', array_map(fn ($id) => '<'.$id.'>', $references));
                    })->first();
                $thread = Conversation::create([
                    'email_inbox_id' => $inbox->id, 'project_id' => $recipient?->campaign->project_id ?? $inbox->default_project_id,
                    'contact_email' => $data['from'], 'subject' => $data['subject'] ?: '(No subject)',
                ]);
            }
            $message = $thread->messages()->create([
                'email_inbox_id' => $inbox->id, 'direction' => 'inbound', 'status' => 'received',
                'from_email' => $data['from'], 'to_email' => $data['to'] ?: $inbox->email_address,
                'subject' => $data['subject'] ?: '(No subject)', 'body' => $data['body'], 'message_id' => $id,
                'in_reply_to' => $data['in_reply_to'] ?? null, 'references' => $data['references'] ?? null,
                'received_at' => $data['received_at'],
            ]);
            $thread->update(['last_message_at' => max($thread->last_message_at ?? now()->subYears(100), Carbon::parse($data['received_at'])), 'read_at' => null]);
            if ($thread->opportunity && ! in_array($thread->opportunity->status, ['placed', 'rejected'])) {
                $thread->opportunity->update(['status' => 'replied']);
            }
            if ($recipient) {
                ReplyMatcher::storeReply($recipient, $data['from'], $data['subject'], $data['body'], $id, $data['in_reply_to'] ?? null, (string) $inbox->id);
            }

            return $message;
        });
    }
}
