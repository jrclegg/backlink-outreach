<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeadBatch extends Model {
    protected $guarded = ['id'];
    public function campaign() { return $this->belongsTo(LeadCampaign::class, 'lead_campaign_id'); }
    public function prospects() { return $this->hasMany(LeadProspect::class); }
}
