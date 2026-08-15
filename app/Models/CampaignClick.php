<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignClick extends Model
{
    protected $fillable = ['campaign_recipient_id', 'url', 'clicked_at', 'ip', 'user_agent'];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function recipient()
    {
        return $this->belongsTo(CampaignRecipient::class);
    }
}
