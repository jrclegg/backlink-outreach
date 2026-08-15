<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BacklinkCheck extends Model
{
    protected $fillable = [
        'campaign_recipient_id', 'target_page_url', 'expected_link_url', 'expected_anchor_text',
        'status', 'check_count', 'last_checked_at', 'next_check_at', 'found_at', 'lost_at'
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'found_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    public function campaignRecipient()
    {
        return $this->belongsTo(CampaignRecipient::class);
    }
}
