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

    Route::post('/logout', function () {
        Auth::logout();
        return redirect('/login');
    })->name('logout');
});

// Fortify routes are auto-registered by Fortify package
