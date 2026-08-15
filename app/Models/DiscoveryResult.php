<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscoveryResult extends Model
{
    protected $fillable = ['discovery_query_id', 'site_url', 'page_title', 'contact_email', 'extraction_status', 'prospect_id'];

    public function discoveryQuery()
    {
        return $this->belongsTo(DiscoveryQuery::class);
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }
}
