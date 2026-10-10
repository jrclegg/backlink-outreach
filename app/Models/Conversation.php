<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['project_id', 'email_inbox_id', 'opportunity_id', 'contact_email', 'subject', 'status', 'last_message_at', 'read_at'];

    protected $casts = ['last_message_at' => 'datetime', 'read_at' => 'datetime'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function emailInbox()
    {
        return $this->belongsTo(EmailInbox::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function messages()
    {
        return $this->hasMany(OutreachMessage::class);
    }
}
