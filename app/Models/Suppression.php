<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    protected $fillable = ['organization_id', 'email', 'reason', 'campaign_recipient_id'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function campaignRecipient()
    {
        return $this->belongsTo(CampaignRecipient::class);
    }
}
