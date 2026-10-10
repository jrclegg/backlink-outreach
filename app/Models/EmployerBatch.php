<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['sources' => 'array'];

    public function campaign()
    {
        return $this->belongsTo(EmployerCampaign::class, 'employer_campaign_id');
    }

    public function items()
    {
        return $this->hasMany(EmployerBatchItem::class);
    }

    public function refreshProgress(): void
    {
        if ($this->status === 'researching' || $this->items()->whereIn('status', ['queued', 'drafting'])->exists()) {
            return;
        }
        $this->update(['status' => $this->items()->where('status', 'failed')->exists() ? 'needs_attention' : 'ready']);
    }
}
