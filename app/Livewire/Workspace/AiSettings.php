<?php

namespace App\Livewire\Workspace;

use App\Services\Outreach\Assistant;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AiSettings extends Component
{
    public string $apiKey = '';

    public string $model = 'claude-sonnet-4-6';

    public string $notice = '';

    public function mount(): void
    {
        $this->model = Auth::user()->currentOrganization->ai_model ?: $this->model;
    }

    public function save(): void
    {
        if (app(Assistant::class)->usesCodex()) {
            $this->notice = app(Assistant::class)->configured(Auth::user()->currentOrganization) ? 'Codex is connected with ChatGPT.' : 'Run codex login on this machine, then check again.';

            return;
        }
        $this->validate(['apiKey' => 'nullable|string|max:500', 'model' => 'required|regex:/^[a-zA-Z0-9._-]+$/|max:100']);
        $org = Auth::user()->currentOrganization;
        $data = ['llm_provider' => 'anthropic', 'ai_model' => $this->model];
        if (trim($this->apiKey)) {
            $data['llm_api_key'] = trim($this->apiKey);
        }
        $org->update($data);
        $this->apiKey = '';
        $this->notice = 'AI settings saved.';
    }

    public function removeKey(): void
    {
        Auth::user()->currentOrganization->update(['llm_api_key' => null]);
        $this->apiKey = '';
        $this->notice = 'API key removed.';
    }

    public function render()
    {
        return view('livewire.workspace.ai-settings', ['usesCodex' => app(Assistant::class)->usesCodex(), 'configured' => app(Assistant::class)->configured(Auth::user()->currentOrganization)])->layout('components.layouts.app', ['title' => 'AI settings']);
    }
}
