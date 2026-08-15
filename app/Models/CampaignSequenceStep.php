<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignSequenceStep extends Model
{
    protected $fillable = ['campaign_id', 'step_number', 'delay_days', 'subject', 'body_html', 'send_if'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
