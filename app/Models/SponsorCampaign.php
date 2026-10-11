<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SponsorCampaign extends Model {
    protected $guarded = ['id'];
    protected $casts = ['enabled'=>'boolean','auto_send'=>'boolean','excluded_domains'=>'array'];
    public function project() { return $this->belongsTo(Project::class); }
    public function batches() { return $this->hasMany(SponsorBatch::class); }
    public function prospects() { return $this->hasMany(SponsorProspect::class); }
}
