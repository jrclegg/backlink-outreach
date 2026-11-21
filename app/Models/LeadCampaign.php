<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
// A general AI outreach campaign: set up from a brief in the UI and run daily by the lead pipeline.
class LeadCampaign extends Model {
    protected $guarded = ['id'];
    protected $casts = ['enabled'=>'boolean','auto_send'=>'boolean','focuses'=>'array','excluded_domains'=>'array','min_words'=>'integer','max_words'=>'integer'];
    public function project() { return $this->belongsTo(Project::class); }
    public function inbox() { return $this->belongsTo(EmailInbox::class, 'email_inbox_id'); }
    public function batches() { return $this->hasMany(LeadBatch::class); }
    public function prospects() { return $this->hasMany(LeadProspect::class); }
    public function mode(): string { return ! $this->enabled ? 'off' : ($this->auto_send ? 'automatic' : 'approval'); }
}
