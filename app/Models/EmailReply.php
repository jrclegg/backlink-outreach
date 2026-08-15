<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailReply extends Model
{
    protected $fillable = [
        'campaign_recipient_id', 'email_inbox_id', 'from_address', 'subject',
        'body_text', 'raw_message_id', 'in_reply_to', 'intent', 'ai_suggested_response', 'received_at'
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function campaignRecipient()
    {
        return $this->belongsTo(CampaignRecipient::class);
    }

    public function emailInbox()
    {
        return $this->belongsTo(EmailInbox::class);
    }
}
