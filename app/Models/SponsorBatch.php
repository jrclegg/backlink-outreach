<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SponsorBatch extends Model {
    protected $guarded = ['id'];
    public function campaign() { return $this->belongsTo(SponsorCampaign::class, 'sponsor_campaign_id'); }
    public function prospects() { return $this->hasMany(SponsorProspect::class); }
}
