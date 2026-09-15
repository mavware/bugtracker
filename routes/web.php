<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// The front door sends anyone signed in straight to their dashboard; the welcome
// page itself stays reachable for them at /welcome.
Route::get('/', HomeController::class)->middleware('guest')->name('home');
Route::get('welcome', HomeController::class)->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.surveillance')->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/surveillance.php';
