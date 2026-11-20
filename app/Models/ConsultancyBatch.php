<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ConsultancyBatch extends Model {
    protected $guarded = ['id'];
    public function campaign() { return $this->belongsTo(ConsultancyCampaign::class, 'consultancy_campaign_id'); }
    public function prospects() { return $this->hasMany(ConsultancyProspect::class); }
}
