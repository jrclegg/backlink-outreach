<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'llm_provider', 'ai_model',
        'llm_api_key',
    ];

    protected $hidden = ['llm_api_key'];

    protected $casts = [
        'llm_api_key' => 'encrypted',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_memberships');
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function emailInboxes()
    {
        return $this->hasMany(EmailInbox::class);
    }

    public function suppressions()
    {
        return $this->hasMany(Suppression::class);
    }
}
