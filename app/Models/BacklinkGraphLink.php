<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BacklinkGraphLink extends Model
{
    protected $fillable = [
        'source_url', 'source_domain', 'target_url', 'target_domain',
        'anchor_text', 'discovered_at', 'discovery_query_id'
    ];

    protected $casts = [
        'discovered_at' => 'datetime',
    ];

    public function discoveryQuery()
    {
        return $this->belongsTo(DiscoveryQuery::class);
    }
}
