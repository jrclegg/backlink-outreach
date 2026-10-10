<?php

use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UnsubscribeController;
use App\Livewire\Campaigns\CampaignBuilder;
use App\Livewire\Campaigns\CampaignShow;
use App\Livewire\Dashboard;
use App\Livewire\Discovery\BacklinkGraphSearch;
use App\Livewire\Discovery\DiscoveryQueryForm;
use App\Livewire\Discovery\DiscoveryResultsList;
use App\Livewire\Inboxes\InboxForm;
use App\Livewire\Inboxes\InboxList;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Prospects\ProspectCreate;
use App\Livewire\Prospects\ProspectImport;
use App\Livewire\Prospects\ProspectsIndex;
use App\Livewire\Prospects\SendProspectEmail;
use App\Livewire\Suppressions\SuppressionList;
use App\Livewire\Workspace\AiSettings;
use App\Livewire\Workspace\ProjectWorkspace;
use App\Livewire\Workspace\UnassignedMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }

    return redirect('/login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/projects/{project}/employer-outreach', \App\Livewire\Workspace\EmployerOutreachWorkspace::class)->name('projects.employer-outreach');

    Route::get('/projects/{project}/workspace', ProjectWorkspace::class)->name('projects.workspace');
    Route::get('/settings/ai', AiSettings::class)->name('ai.settings');
    Route::get('/mail/unassigned', UnassignedMail::class)->name('mail.unassigned');

    Route::get('/projects', ProjectList::class)->name('projects.index');

    Route::get('/inboxes', InboxList::class)->name('inboxes.index');
    Route::get('/inboxes/add', InboxForm::class)->name('inboxes.create');
    Route::get('/inboxes/{id}/edit', InboxForm::class)->name('inboxes.edit');

    Route::get('/prospects', ProspectsIndex::class)->name('prospects.index');
    Route::get('/prospects/add', ProspectCreate::class)->name('prospects.create');
    Route::get('/prospects/{prospect}/email', SendProspectEmail::class)->name('prospects.email');
    Route::get('/prospects/import', ProspectImport::class)->name('prospects.import');

    Route::get('/campaigns/new', CampaignBuilder::class)->name('campaigns.create');
    Route::get('/campaigns/{campaign}', CampaignShow::class)->name('campaigns.show');

    // Discovery routes
    Route::get('/discover', DiscoveryQueryForm::class)->name('discover.index');
    Route::get('/discover/{query}', DiscoveryResultsList::class)->name('discover.results');
    Route::get('/backlink-graph/search', BacklinkGraphSearch::class)->name('backlink-graph.search');

    // Suppression routes
    Route::get('/suppressions', SuppressionList::class)->name('suppressions.index');

    Route::post('/logout', function () {
        Auth::logout();

        return redirect('/login');
    })->name('logout');
});

// Public unsubscribe routes
Route::get('/u/{token}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
Route::post('/u/{token}', [UnsubscribeController::class, 'store'])->name('unsubscribe.store');

// Public tracking routes
Route::get('/t/o/{token}.gif', [TrackingController::class, 'open'])->name('tracking.open');
Route::get('/t/c/{token}', [TrackingController::class, 'click'])->name('tracking.click');

// Fortify routes are auto-registered by Fortify package
