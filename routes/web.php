<?php

use App\Livewire\Dashboard;
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

    Route::get('/projects', App\Livewire\Projects\ProjectList::class)->name('projects.index');

    Route::get('/inboxes', App\Livewire\Inboxes\InboxList::class)->name('inboxes.index');
    Route::get('/inboxes/add', App\Livewire\Inboxes\InboxForm::class)->name('inboxes.create');

    Route::get('/prospects/import', App\Livewire\Prospects\ProspectImport::class)->name('prospects.import');

    Route::get('/campaigns/new', App\Livewire\Campaigns\CampaignBuilder::class)->name('campaigns.create');
    Route::get('/campaigns/{campaign}', App\Livewire\Campaigns\CampaignShow::class)->name('campaigns.show');

    Route::post('/logout', function () {
        Auth::logout();
        return redirect('/login');
    })->name('logout');
});

// Public tracking routes
Route::get('/t/o/{token}.gif', [App\Http\Controllers\TrackingController::class, 'open'])->name('tracking.open');
Route::get('/t/c/{token}', [App\Http\Controllers\TrackingController::class, 'click'])->name('tracking.click');

// Fortify routes are auto-registered by Fortify package
