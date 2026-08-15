<?php

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/projects', App\Livewire\Projects\ProjectList::class)->name('projects.index');
    Route::get('/inboxes', App\Livewire\Inboxes\InboxList::class)->name('inboxes.index');
});

// Fortify routes are auto-registered by Fortify package
