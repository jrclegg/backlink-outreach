<?php

namespace App\Livewire\Prospects;

use App\Models\Project;
use App\Models\Prospect;
use Illuminate\Support\Facades\Auth;
use League\Csv\Reader;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProspectImport extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:csv,txt')]
    public $file;

    public $projectId;
    public $imported = 0;
    public $skipped = 0;

    public function import()
    {
        $this->validate();

        try {
            $csv = Reader::createFromPath($this->file->getRealPath());
            $csv->setHeaderOffset(0);
            $records = $csv->getRecords();

            $org = Auth::user()->currentOrganization;
            $project = Project::find($this->projectId);

            if (!$project || $project->organization_id !== $org->id) {
                $this->addError('file', 'Invalid project');
                return;
            }

            foreach ($records as $record) {
                $email = trim($record['email'] ?? $record['contact_email'] ?? '');
                $name = trim($record['name'] ?? $record['contact_name'] ?? '');
                $url = trim($record['website'] ?? $record['site_url'] ?? '');

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->skipped++;
                    continue;
                }

                if (!$url) {
                    $this->skipped++;
                    continue;
                }

                try {
                    Prospect::firstOrCreate(
                        ['project_id' => $project->id, 'contact_email' => $email],
                        [
                            'site_url' => $url,
                            'contact_name' => $name,
                            'status' => 'new',
                            'source' => 'csv_import',
                        ]
                    );
                    $this->imported++;
                } catch (\Exception $e) {
                    $this->skipped++;
                }
            }
        } catch (\Exception $e) {
            $this->addError('file', 'Error reading CSV: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $projects = $org ? $org->projects : collect();

        return view('livewire.prospects.prospect-import', [
            'projects' => $projects,
        ])->layout('components.layouts.app', ['title' => 'Import Prospects']);
    }
}
