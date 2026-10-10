<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailInbox extends Model
{
    protected $fillable = [
        'default_project_id', 'provider', 'sync_error', 'last_polled_at', 'organization_id', 'name', 'email_address', 'from_name',
        'from_address', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
        'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption', 'imap_folder',
        'max_sends_per_hour', 'is_active',
    ];

    protected $hidden = ['smtp_password', 'imap_password'];

    protected $casts = [
        'smtp_password' => 'encrypted',
        'imap_password' => 'encrypted',
        'last_polled_at' => 'datetime',
    ];

    public function getSenderAddressAttribute(): string
    {
        return $this->from_address ?: $this->email_address;
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'email_inbox_project');
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function replies()
    {
        return $this->hasMany(EmailReply::class);
    }

    public function getConnectionStatus(): string
    {
        if (! $this->smtp_host) {
            return 'not_connected';
        }

        return 'connected';
    }
}
