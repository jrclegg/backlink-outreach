<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeadCampaign extends Model {
    protected $guarded = ['id'];
    protected $casts = ['enabled'=>'boolean','auto_send'=>'boolean','focuses'=>'array','excluded_domains'=>'array'];
    public function project() { return $this->belongsTo(Project::class); }
    public function batches() { return $this->hasMany(LeadBatch::class); }
    public function prospects() { return $this->hasMany(LeadProspect::class); }
}
