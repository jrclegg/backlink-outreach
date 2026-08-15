<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prospect extends Model
{
    protected $fillable = [
        'project_id', 'site_url', 'contact_name', 'contact_email',
        'niche', 'notes', 'status', 'source', 'discovery_query_id'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function discoveryQuery()
    {
        return $this->belongsTo(DiscoveryQuery::class);
    }

    public function campaignRecipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
