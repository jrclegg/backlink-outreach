<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerContact extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['verified_at' => 'datetime'];

    public function campaign()
    {
        return $this->belongsTo(EmployerCampaign::class, 'employer_campaign_id');
    }

    public function item()
    {
        return $this->hasOne(EmployerBatchItem::class);
    }
}
