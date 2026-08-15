<?php

namespace App\Livewire\Campaigns;

use App\Jobs\DispatchCampaignSends;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CampaignBuilder extends Component
{
    public $projectId;
    public $inboxId;
    public $campaignName = '';
    public $personalizationMode = 'static';

    public $steps = [];
    public $selectedProspectIds = [];
    public $showConfirm = false;

    public $currentStep = 'basic';

    #[Validate('required|string')]
    public $stepSubject = '';

    #[Validate('required|string')]
    public $stepBody = '';

    public function addStep()
    {
        $stepNum = count($this->steps) + 1;
        $this->steps[] = [
            'number' => $stepNum,
            'delay_days' => $stepNum > 1 ? 3 : 0,
            'subject' => '',
            'body' => '',
            'send_if' => 'no_reply',
        ];
    }

    public function removeStep($index)
    {
        array_splice($this->steps, $index, 1);
    }

    public function updateStep($index, $key, $value)
    {
        $this->steps[$index][$key] = $value;
    }

    public function selectAllProspects()
    {
        $org = Auth::user()->currentOrganization;
        $project = Project::find($this->projectId);

        if ($project && $project->organization_id === $org->id) {
            $this->selectedProspectIds = $project->prospects()->pluck('id')->toArray();
        }
    }

    public function sendCampaign()
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'inboxId' => 'required|exists:email_inboxes,id',
            'campaignName' => 'required|string',
        ]);

        $org = Auth::user()->currentOrganization;
        $project = Project::find($this->projectId);

        if (!$project || $project->organization_id !== $org->id) {
            $this->addError('projectId', 'Invalid project');
            return;
        }

        if (empty($this->selectedProspectIds)) {
            $this->addError('selectedProspectIds', 'No prospects selected');
            return;
        }

        if (empty($this->steps)) {
            $this->addError('steps', 'Add at least one email step');
            return;
        }

        // Create campaign
        $campaign = Campaign::create([
            'project_id' => $this->projectId,
            'email_inbox_id' => $this->inboxId,
            'name' => $this->campaignName,
            'personalization_mode' => $this->personalizationMode,
            'status' => 'queued',
        ]);

        // Create sequence steps
        foreach ($this->steps as $stepData) {
            $campaign->sequenceSteps()->create([
                'step_number' => $stepData['number'],
                'delay_days' => $stepData['delay_days'],
                'subject' => $stepData['subject'],
                'body_html' => $stepData['body'],
                'send_if' => $stepData['send_if'],
            ]);
        }

        // Create recipients
        foreach ($this->selectedProspectIds as $prospectId) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'prospect_id' => $prospectId,
                'status' => 'pending',
            ]);
        }

        // Dispatch sends
        DispatchCampaignSends::dispatch($campaign);

        return $this->redirect(route('campaigns.show', $campaign->id));
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $projects = $org ? $org->projects : collect();
        $project = $this->projectId ? Project::find($this->projectId) : null;
        $prospects = $project ? $project->prospects : collect();
        $inboxes = $org ? $org->emailInboxes : collect();

        return view('livewire.campaigns.campaign-builder', [
            'projects' => $projects,
            'prospects' => $prospects,
            'inboxes' => $inboxes,
            'selectedCount' => count($this->selectedProspectIds),
        ])->layout('components.layouts.app', ['title' => 'Create Campaign']);
    }
}
