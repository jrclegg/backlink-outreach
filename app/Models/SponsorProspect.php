<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SponsorProspect extends Model {
    protected $guarded = ['id'];
    protected $casts = ['review'=>'array','reviewed_at'=>'datetime'];
    public function campaign() { return $this->belongsTo(SponsorCampaign::class, 'sponsor_campaign_id'); }
    public function batch() { return $this->belongsTo(SponsorBatch::class, 'sponsor_batch_id'); }
    public function message() { return $this->belongsTo(OutreachMessage::class, 'outreach_message_id'); }
}
