<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = ['project_id', 'email_inbox_id', 'name', 'personalization_mode', 'status', 'scheduled_at'];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function emailInbox()
    {
        return $this->belongsTo(EmailInbox::class);
    }

    public function sequenceSteps()
    {
        return $this->hasMany(CampaignSequenceStep::class)->orderBy('step_number');
    }

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
