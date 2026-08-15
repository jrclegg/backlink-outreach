<?php

namespace App\Livewire\Suppressions;

use App\Models\Suppression;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SuppressionList extends Component
{
    use WithPagination;

    public string $searchEmail = '';

    public function updatingSearchEmail()
    {
        $this->resetPage();
    }

    public function removeSuppression(int $suppressionId)
    {
        try {
            $org = Auth::user()->currentOrganization;
            $suppression = Suppression::where('organization_id', $org->id)->find($suppressionId);

            if (!$suppression) {
                $this->addError('suppression', 'Suppression not found');
                return;
            }

            $email = $suppression->email;
            $suppression->delete();

            session()->flash('success', "Removed suppression for $email");
        } catch (\Exception $e) {
            $this->addError('suppression', 'Failed to remove suppression: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;

        $suppressions = Suppression::where('organization_id', $org->id)
            ->when($this->searchEmail, fn($q) => $q->where('email', 'LIKE', '%' . $this->searchEmail . '%'))
            ->latest('created_at')
            ->paginate(20);

        return view('livewire.suppressions.suppression-list', [
            'suppressions' => $suppressions,
        ])->layout('components.layouts.app', ['title' => 'Suppression List']);
    }
}
