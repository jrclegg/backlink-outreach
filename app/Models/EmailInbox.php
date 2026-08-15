<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailInbox extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'email_address', 'from_name',
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
        'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption', 'imap_folder',
        'max_sends_per_hour', 'is_active',
        'oauth_provider', 'oauth_access_token', 'oauth_refresh_token', 'oauth_expires_at', 'last_verified_at'
    ];

    protected $casts = [
        'smtp_password' => 'encrypted',
        'imap_password' => 'encrypted',
        'oauth_access_token' => 'encrypted',
        'oauth_refresh_token' => 'encrypted',
        'last_polled_at' => 'datetime',
        'oauth_expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

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

    public function isOAuthConnected(): bool
    {
        return $this->oauth_provider !== null && $this->oauth_access_token !== null;
    }

    public function isTokenExpired(): bool
    {
        if (!$this->oauth_expires_at) {
            return false;
        }
        return now()->isAfter($this->oauth_expires_at);
    }

    public function getConnectionStatus(): string
    {
        if (!$this->isOAuthConnected() && !$this->smtp_host) {
            return 'not_connected';
        }

        if ($this->isTokenExpired()) {
            return 'token_expired';
        }

        return 'connected';
    }
}
