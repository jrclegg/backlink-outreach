<?php
namespace App\Services\Outreach;
use App\Jobs\ResearchLeadBatch;
use App\Jobs\SendReviewedMessage;
use App\Models\LeadBatch;
use App\Models\LeadCampaign;
use App\Models\LeadProspect;
use App\Models\OutreachMessage;
use App\Models\Suppression;
use Illuminate\Support\Facades\DB;
class LeadOutreach {
    public function start(LeadCampaign $campaign): LeadBatch {
        $batch = LeadBatch::firstOrCreate(['lead_campaign_id'=>$campaign->id,'day'=>now('Europe/London')->toDateString()]);
        if ($batch->wasRecentlyCreated) ResearchLeadBatch::dispatch($batch);
        return $batch;
    }
    public function eligible(LeadProspect $p): bool {
        $c = $p->campaign; $org = $c->project->organization_id;
        foreach ($c->excluded_domains ?? [] as $domain) {
            if ($p->domain === $domain || str_ends_with($p->domain, '.'.$domain)) return false;
        }
        if ($p->status === 'excluded' || Suppression::where('organization_id',$org)->where('email',$p->email)->exists()) return false;
        // One approach per business, across all of the organisation's projects.
        return ! OutreachMessage::whereHas('emailInbox',fn($q)=>$q->where('organization_id',$org))
            ->where(fn($q)=>$q->where('to_email','like','%@'.$p->domain)->orWhere('from_email','like','%@'.$p->domain))
            ->whereIn('status',['queued','sending','sent','received'])->when($p->outreach_message_id,fn($q)=>$q->where('id','!=',$p->outreach_message_id))->exists();
    }
    public static function signature(LeadProspect $p): string {
        return hash('sha256',json_encode([$p->email,$p->source_url,$p->detail_url,$p->detail,$p->campaign->email_inbox_id,$p->message?->subject,$p->message?->body]));
    }
    public function approvalCount(LeadCampaign $c): int {
        return OutreachMessage::whereIn('id',$c->prospects()->pluck('outreach_message_id'))->where('approved_at','>=',now('Europe/London')->startOfDay())->count();
    }
    public function approve(LeadProspect $prospect): bool {
        return DB::transaction(function () use ($prospect) {
            $p = LeadProspect::whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            $c = LeadCampaign::whereKey($p->lead_campaign_id)->lockForUpdate()->firstOrFail();
            if (!$c->enabled || !$c->auto_send || $p->status !== 'passed' || !$p->reviewed_at || $p->reviewed_at->lt(now()->subDay()) || !$this->eligible($p) || self::signature($p) !== $p->signature || $this->approvalCount($c) >= $c->daily_limit) return false;
            $m = $p->message;
            $inbox = $c->project->emailInboxes()->whereKey($c->email_inbox_id)->where('is_active',true)->first();
            if (!$inbox || $inbox->organization_id !== $c->project->organization_id || !$m || $m->status !== 'draft' || $m->to_email !== $p->email || $m->email_inbox_id !== $inbox->id || $m->conversation->project_id !== $c->project_id) return false;
            // 'approved' marks an automatic send, so delivery re-checks the quality gate; drafts you send yourself stay 'passed'.
            $p->update(['status'=>'approved']);
            $m->update(['status'=>'queued','approved_at'=>now()]);
            SendReviewedMessage::dispatch($m)->afterCommit();
            return true;
        });
    }
}
