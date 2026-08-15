<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id', 'prospect_id', 'token', 'message_id', 'status', 'error',
        'current_step', 'next_step_send_at', 'ai_intro_text', 'anchor_text',
        'sent_at', 'opened_at', 'first_clicked_at', 'replied_at', 'open_count', 'click_count'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
        'replied_at' => 'datetime',
        'next_step_send_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->token)) {
                $model->token = Str::uuid();
            }
        });
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    public function replies()
    {
        return $this->hasMany(EmailReply::class);
    }

    public function clicks()
    {
        return $this->hasMany(CampaignClick::class);
    }

    public function backlinkCheck()
    {
        return $this->hasOne(BacklinkCheck::class);
    }
}
