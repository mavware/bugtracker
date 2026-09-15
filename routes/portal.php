<?php

use Illuminate\Support\Facades\Route;

// The client portal: where a property's owner sees the nights a professional
// recorded for them and tidies their names and rooms. There is no role for it —
// the pages show whatever customer records are linked to the signed-in account,
// and the invitation route is how a record gets linked.
Route::middleware(['auth', 'verified'])->prefix('portal')->group(function () {
    Route::livewire('/', 'pages::portal.index')->name('portal.index');
    Route::livewire('rooms', 'pages::portal.rooms')->name('portal.rooms');
    Route::livewire('invitations/{token}', 'pages::portal.invitation')->name('portal.invitations.show');
});
