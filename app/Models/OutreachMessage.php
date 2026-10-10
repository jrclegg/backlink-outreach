<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutreachMessage extends Model
{
    protected $fillable = ['conversation_id', 'email_inbox_id', 'direction', 'status', 'from_email', 'to_email', 'subject', 'body', 'message_id', 'in_reply_to', 'references', 'error', 'approved_at', 'sent_at', 'received_at'];

    protected $casts = ['approved_at' => 'datetime', 'sent_at' => 'datetime', 'received_at' => 'datetime'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function emailInbox()
    {
        return $this->belongsTo(EmailInbox::class);
    }
}
