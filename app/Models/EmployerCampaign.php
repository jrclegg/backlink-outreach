<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerCampaign extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'auto_send' => 'boolean', 'discover' => 'boolean'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function emailInbox()
    {
        return $this->belongsTo(EmailInbox::class);
    }

    public function leads()
    {
        return $this->hasMany(EmployerLead::class);
    }

    public function contacts()
    {
        return $this->hasMany(EmployerContact::class);
    }

    public function batches()
    {
        return $this->hasMany(EmployerBatch::class);
    }
}
