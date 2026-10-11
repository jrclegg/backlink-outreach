<?php
namespace App\Services\Outreach;
use App\Jobs\ResearchSponsorBatch;
use App\Jobs\SendReviewedMessage;
use App\Models\SponsorCampaign;
use App\Models\SponsorBatch;
use App\Models\SponsorProspect;
use App\Models\EmployerContact;
use App\Models\OutreachMessage;
use App\Models\Suppression;
use Illuminate\Support\Facades\DB;
class SponsorOutreach {
    public function start(SponsorCampaign $campaign): SponsorBatch {
        $batch = SponsorBatch::firstOrCreate(['sponsor_campaign_id'=>$campaign->id,'day'=>now('Europe/London')->toDateString()]);
        if ($batch->wasRecentlyCreated) ResearchSponsorBatch::dispatch($batch);
        return $batch;
    }
    public static function domain(string $url): string { return preg_replace('/^www\./', '', strtolower(parse_url($url, PHP_URL_HOST) ?? '')); }
    public function eligible(SponsorProspect $p): bool {
        $c = $p->campaign;
        foreach ($c->excluded_domains ?? [] as $domain) {
            if ($p->domain === $domain || str_ends_with($p->domain, '.'.$domain)) return false;
        }
        // Keep sponsor pitches away from anyone the employer campaign has found, contacted or not.
        if (EmployerContact::whereHas('campaign', fn ($q) => $q->where('project_id', $c->project_id))->where('email', $p->email)->exists()) return false;
        if ($p->status === 'excluded' || Suppression::where('organization_id',$c->project->organization_id)->where('email',$p->email)->exists()) return false;
        return ! OutreachMessage::whereHas('conversation',fn($q)=>$q->where('project_id',$c->project_id))
            ->where(fn($q)=>$q->where('to_email',$p->email)->orWhere('from_email',$p->email))
            ->whereIn('status',['queued','sending','sent','received'])->when($p->outreach_message_id,fn($q)=>$q->where('id','!=',$p->outreach_message_id))->exists();
    }
    public static function signature(SponsorProspect $p): string {
        return hash('sha256',json_encode([$p->email,$p->source_url,$p->product_url,$p->relevance,$p->campaign->offer,$p->campaign->booking_url,$p->campaign->email_inbox_id,$p->message?->subject,$p->message?->body]));
    }
    public function approvalCount(SponsorCampaign $c): int {
        return OutreachMessage::whereIn('id',$c->prospects()->pluck('outreach_message_id'))->where('approved_at','>=',now('Europe/London')->startOfDay())->count();
    }
    public function approve(SponsorProspect $prospect): bool {
        return DB::transaction(function () use ($prospect) {
            $p = SponsorProspect::whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            $c = SponsorCampaign::whereKey($p->sponsor_campaign_id)->lockForUpdate()->firstOrFail();
            if (!$c->enabled || !$c->auto_send || $p->status !== 'passed' || !$p->reviewed_at || $p->reviewed_at->lt(now()->subDay()) || !$this->eligible($p) || self::signature($p) !== $p->signature || $this->approvalCount($c) >= $c->daily_limit) return false;
            $m = $p->message;
            $inbox = $c->project->emailInboxes()->whereKey($c->email_inbox_id)->where('is_active',true)->first();
            if (!$inbox || $inbox->organization_id !== $c->project->organization_id || !$m || $m->status !== 'draft' || $m->to_email !== $p->email || $m->email_inbox_id !== $inbox->id || $m->conversation->project_id !== $c->project_id) return false;
            $m->update(['status'=>'queued','approved_at'=>now()]);
            SendReviewedMessage::dispatch($m)->afterCommit();
            return true;
        });
    }
}
