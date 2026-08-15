<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['organization_id', 'name', 'website_url', 'notes'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function emailInboxes()
    {
        return $this->belongsToMany(EmailInbox::class, 'email_inbox_project');
    }

    public function prospects()
    {
        return $this->hasMany(Prospect::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function discoveryQueries()
    {
        return $this->hasMany(DiscoveryQuery::class);
    }
}
