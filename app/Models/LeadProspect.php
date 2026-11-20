<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeadProspect extends Model {
    protected $guarded = ['id'];
    protected $casts = ['review'=>'array','reviewed_at'=>'datetime'];
    public function campaign() { return $this->belongsTo(LeadCampaign::class, 'lead_campaign_id'); }
    public function batch() { return $this->belongsTo(LeadBatch::class, 'lead_batch_id'); }
    public function message() { return $this->belongsTo(OutreachMessage::class, 'outreach_message_id'); }
}
